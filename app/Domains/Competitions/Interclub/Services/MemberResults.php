<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
use Illuminate\Support\Collection;

/**
 * A member's own results, interclub and official tournaments.
 *
 * The one place the two are ever counted together, and only for the member
 * themselves: an interclub win rate is what captains decide on, and a
 * tournament — where a player meets their own serie — says nothing about it.
 * So the combined figure never travels alone: every total carries the split
 * it was made of.
 */
class MemberResults
{
    /**
     * Every match of both kinds as one list, newest first.
     *
     * Interclub lines carry a link to their tie; a tournament line has nowhere
     * to go — the federation gives it no identity of its own.
     *
     * @param  Collection<int, InterclubIndividualMatch>  $interclub
     * @param  Collection<int, OfficialTournamentMatch>  $tournaments
     * @return Collection<int, array<string, mixed>>
     */
    public function feed(User $member, Collection $interclub, Collection $tournaments): Collection
    {
        return $interclub->map(fn (InterclubIndividualMatch $line): array => $this->interclubEntry($member, $line))
            ->concat($tournaments->map(fn (OfficialTournamentMatch $line): array => $this->tournamentEntry($line)))
            ->sortByDesc(fn (array $entry): int => $entry['date']->getTimestamp())
            ->values();
    }

    /**
     * @return Collection<int, InterclubIndividualMatch>
     */
    public function interclubLines(int $userId, int $seasonId = 0): Collection
    {
        return InterclubIndividualMatch::with([
            'interclub.season',
            'interclub.visitedTeam.club',
            'interclub.visitingTeam.club',
        ])
            ->where('user_id', $userId)
            ->when($seasonId > 0, fn ($query) => $query->whereHas(
                'interclub', fn ($sub) => $sub->where('season_id', $seasonId)
            ))
            ->get()
            ->sortByDesc(fn (InterclubIndividualMatch $line) => $line->interclub->start_date_time)
            ->values();
    }

    /**
     * Played, won and rate for each kind, and for both together.
     *
     * @param  Collection<int, InterclubIndividualMatch>  $interclub
     * @param  Collection<int, OfficialTournamentMatch>  $tournaments
     * @return array{all: array{played: int, won: int, rate: int}, interclub: array{played: int, won: int, rate: int, ties: int}, tournaments: array{played: int, won: int, rate: int, tournaments: int}}
     */
    public function totals(Collection $interclub, Collection $tournaments): array
    {
        $interclubWon = $interclub->where('we_won', true)->count();
        $tournamentWon = $tournaments->where('we_won', true)->count();

        return [
            'all' => $this->tally($interclub->count() + $tournaments->count(), $interclubWon + $tournamentWon),
            'interclub' => [
                ...$this->tally($interclub->count(), $interclubWon),
                'ties' => $interclub->pluck('interclub_id')->unique()->count(),
            ],
            'tournaments' => [
                ...$this->tally($tournaments->count(), $tournamentWon),
                'tournaments' => $tournaments
                    ->map(fn (OfficialTournamentMatch $line): string => $line->played_on->toDateString() . '|' . $line->tournament_name)
                    ->unique()
                    ->count(),
            ],
        ];
    }

    /**
     * Newest first; within a day, in the order the federation listed them.
     *
     * @return Collection<int, OfficialTournamentMatch>
     */
    public function tournamentLines(int $userId, int $seasonId = 0): Collection
    {
        return OfficialTournamentMatch::with('season')
            ->where('user_id', $userId)
            ->when($seasonId > 0, fn ($query) => $query->where('season_id', $seasonId))
            ->orderByDesc('played_on')
            ->orderBy('id')
            ->get();
    }

    /**
     * Tournament matches by season, then tournament (newest first), then serie.
     *
     * A tournament is its name on its day: the federation gives it no id, and
     * the same club runs the same criterium several times a season.
     *
     * @param  Collection<int, OfficialTournamentMatch>  $tournaments
     * @return Collection<array-key, Collection<int, array<string, mixed>>>
     */
    public function tournamentsBySeason(Collection $tournaments): Collection
    {
        return $tournaments
            ->groupBy(fn (OfficialTournamentMatch $line): string => $line->season->name)
            ->map(fn (Collection $seasonLines): Collection => $seasonLines
                ->groupBy(fn (OfficialTournamentMatch $line): string => $line->played_on->toDateString() . '|' . $line->tournament_name)
                ->map(fn (Collection $lines): array => $this->tournament($lines))
                ->sortByDesc(fn (array $tournament): int => $tournament['date']->getTimestamp())
                ->values());
    }

    /**
     * @return array<string, mixed>
     */
    private function interclubEntry(User $member, InterclubIndividualMatch $line): array
    {
        $fixture = $line->interclub;
        $ourTeam = $fixture->playerTeam($member) ?? $fixture->ourTeam();
        $isHome = $ourTeam !== null && $fixture->visited_team_id === $ourTeam->id;
        $opponentTeam = ($isHome ? $fixture->visitingTeam : $fixture->visitedTeam)?->fullName() ?? '—';

        return [
            'context' => __('Interclub') . ' · vs ' . $opponentTeam,
            'date' => $fixture->start_date_time,
            'is_forfeit' => $line->is_forfeit,
            'key' => 'i' . $line->id,
            'kind' => 'interclub',
            'link' => route('admin.interclubs.my-match', $fixture->id),
            'opponent' => $line->opponent_name ?? '—',
            'opponent_ranking' => $line->opponent_ranking,
            'score' => $line->setScore(),
            'won' => $line->we_won,
        ];
    }

    /**
     * @return array{played: int, won: int, rate: int}
     */
    private function tally(int $played, int $won): array
    {
        return [
            'played' => $played,
            'rate' => $played > 0 ? (int) round($won / $played * 100) : 0,
            'won' => $won,
        ];
    }

    /**
     * One tournament on its day, its lines split by serie.
     *
     * Keyed for the page by its first line: the federation gives the
     * tournament itself nothing to key on.
     *
     * @param  Collection<int, OfficialTournamentMatch>  $lines
     * @return array<string, mixed>
     */
    private function tournament(Collection $lines): array
    {
        return [
            'date' => $lines->first()->played_on,
            'key' => (string) $lines->first()->id,
            'name' => $lines->first()->tournament_name,
            'played' => $lines->count(),
            'series' => $lines->groupBy(fn (OfficialTournamentMatch $line): string => $line->serie_name ?? '—'),
            'won' => $lines->where('we_won', true)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tournamentEntry(OfficialTournamentMatch $line): array
    {
        return [
            'context' => collect([$line->tournament_name, $line->serie_name])->filter()->implode(' · '),
            'date' => $line->played_on,
            'is_forfeit' => false,
            'key' => 't' . $line->id,
            'kind' => 'tournament',
            'link' => null,
            'opponent' => $line->opponent_name,
            'opponent_ranking' => $line->opponent_ranking,
            'score' => $line->setScore(),
            'won' => $line->we_won,
        ];
    }
}
