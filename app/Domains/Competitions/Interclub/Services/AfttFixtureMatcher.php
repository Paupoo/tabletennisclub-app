<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\AfttMatch;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use Illuminate\Support\Collection;

/**
 * Gives a federation identifier to fixtures that were typed in by hand.
 *
 * The calendar import keys everything on `interclubs.aftt_match_id`, so a
 * season entered before the club ever spoke to TabT is invisible to it: it
 * finds none of those rows, creates a second set beside them, and the orphan
 * sweep — which only looks at rows that already carry an identifier — leaves
 * the originals standing. The result is a season in duplicate, with no error
 * anywhere. This runs first and closes that gap.
 *
 * Pairing is on the two teams rather than on the date, because a postponed
 * fixture is exactly the case that matters: the federation moved it, the club
 * wrote down the old date, and the two rows are still the same match. Where a
 * pair meets more than once with the same side at home, the date decides.
 *
 * Nothing is guessed. A federation fixture that finds no local counterpart, or
 * finds more than one it cannot separate, is named in the report and left
 * alone — the calendar import will then create it, which is the right outcome
 * for a match the club never recorded.
 */
class AfttFixtureMatcher
{
    /** @var array<int, int> local fixture ids this run paired */
    private array $linkedIds = [];

    /** @var array<string, array<int, string>> */
    private array $report = [
        'ambiguous' => [],
        'unmatched' => [],
    ];

    /**
     * @return array{linked: int, linked_ids: array<int, int>, report: array<string, array<int, string>>}
     */
    public function link(Season $season, int $afttSeason, string $clubCode, TabtClient $client, bool $dryRun = false): array
    {
        foreach ($this->divisionsOf($clubCode, $afttSeason, $client) as $divisionId) {
            foreach ($client->divisionMatches($divisionId, $afttSeason) as $match) {
                if ($match->isBye || ! $this->involvesUs($match, $clubCode)) {
                    continue;
                }

                $this->linkOne($season, $match, $dryRun);
            }
        }

        return [
            'linked' => count($this->linkedIds),
            // Named, not just counted, so a dry run can say which local rows
            // would stop being orphans — otherwise it reports every one of them
            // as a duplicate waiting to happen, including the ones it just
            // decided to rescue.
            'linked_ids' => $this->linkedIds,
            'report' => $this->report,
        ];
    }

    /**
     * Candidate local rows for this fixture: the same two teams, either way
     * round already excluded by the column they sit in, and no identifier yet.
     *
     * @return Collection<int, Interclub>
     */
    private function candidates(Season $season, Team $home, Team $away): Collection
    {
        return Interclub::where('season_id', $season->id)
            ->whereNull('aftt_match_id')
            ->where('visited_team_id', $home->id)
            ->where('visiting_team_id', $away->id)
            ->get();
    }

    /**
     * @return array<int, int>
     */
    private function divisionsOf(string $clubCode, int $afttSeason, TabtClient $client): array
    {
        return collect($client->clubTeams($clubCode, $afttSeason))
            ->pluck('divisionId')
            ->unique()
            ->values()
            ->all();
    }

    private function involvesUs(AfttMatch $match, string $clubCode): bool
    {
        return $match->homeClub === $clubCode || $match->awayClub === $clubCode;
    }

    private function label(AfttMatch $match): string
    {
        return $match->matchId . ' — ' . $match->homeTeam . ' vs ' . $match->awayTeam
            . ' (' . ($match->date?->format('d/m/Y') ?? '?') . ')';
    }

    private function linkOne(Season $season, AfttMatch $match, bool $dryRun): void
    {
        $home = $this->localTeam($season, $match->homeClub, $match->homeTeam, $match->divisionCategory);
        $away = $this->localTeam($season, $match->awayClub, $match->awayTeam, $match->divisionCategory);

        if (! $home instanceof Team || ! $away instanceof Team) {
            $this->report['unmatched'][] = $this->label($match);

            return;
        }

        $candidates = $this->candidates($season, $home, $away);

        if ($candidates->isEmpty()) {
            $this->report['unmatched'][] = $this->label($match);

            return;
        }

        $chosen = $candidates->count() === 1
            ? $candidates->first()
            : $this->nearestTo($candidates, $match);

        if (! $chosen instanceof Interclub) {
            $this->report['ambiguous'][] = $this->label($match)
                . ' — ' . $candidates->count() . ' local fixtures, none closer than another';

            return;
        }

        $this->linkedIds[] = $chosen->id;

        if (! $dryRun) {
            $chosen->update(['aftt_match_id' => $match->matchId]);
        }
    }

    /**
     * Our team behind a federation club code and team name.
     *
     * The letter alone is not enough — a club fields an "A" in men and an "A"
     * in veterans — and the league carries no federation division id on a
     * season nobody ever imported. The category the federation states about the
     * division is what separates them.
     */
    private function localTeam(Season $season, string $clubCode, string $teamName, int $divisionCategory): ?Team
    {
        $category = LeagueCategory::fromFederationCategory($divisionCategory);

        if (! $category instanceof LeagueCategory) {
            return null;
        }

        $words = preg_split('/\s+/', trim($teamName)) ?: [];
        $letter = (string) end($words);

        if ($letter === '') {
            return null;
        }

        return Team::where('teams.season_id', $season->id)
            ->where('teams.name', $letter)
            ->whereHas('club', fn ($query) => $query->where('licence', $clubCode))
            ->whereHas('league', fn ($query) => $query->where('category', $category->name))
            ->first();
    }

    /**
     * The candidate played on the federation's date, or the closest to it.
     *
     * Only reached when a pair meets twice with the same side at home, which
     * happens in a division played over three rounds. A tie of more than a
     * fortnight either way is refused rather than resolved: two fixtures that
     * far apart are not two readings of the same evening.
     *
     * @param  Collection<int, Interclub>  $candidates
     */
    private function nearestTo(Collection $candidates, AfttMatch $match): ?Interclub
    {
        if ($match->date === null) {
            return null;
        }

        // `diffInDays` counts in fractions of a day, so the gap is a float.
        $gap = fn (Interclub $fixture): float => abs(
            $fixture->start_date_time->diffInDays($match->date, absolute: true)
        );

        $closest = $candidates->sortBy($gap)->first();

        return $gap($closest) <= 14.0 ? $closest : null;
    }
}
