<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\WeeklyFixture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\Ranking;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * « Sélections de la semaine » : les rencontres du club sur une fenêtre, prêtes
 * à afficher en tuiles groupées par jour. La page et le mail du jeudi lisent la
 * même chose.
 *
 * Décidé le 2026-10-11 : seule une compo envoyée (`selection_confirmed_at`)
 * nomme des joueurs ; un forfait reste visible, sans joueurs ; un bye n'est pas
 * une rencontre.
 *
 * Décidé le 2026-10-15 : un bye n'est pas une tuile, mais une équipe absente
 * des tuiles étonne. Les byes de la fenêtre sont donc nommés à part, sans jour
 * ni heure : leur date n'est qu'une déduction de l'import.
 */
class WeeklySelections
{
    /**
     * The competition week (1, 2, 3…) the fixture belongs to, counted as the
     * other interclub screens count their « journées ».
     *
     * @var array<int, array<int, int>> season id => week_number => match day
     */
    private array $matchDayMaps = [];

    /**
     * The fixtures of the window, grouped by day (`Y-m-d`), each day in the
     * order of the clock, then category, then team letter.
     *
     * @return Collection<string, Collection<int, WeeklyFixture>>
     */
    public function between(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Interclub::query()
            ->with(['league', 'visitedTeam.club', 'visitingTeam.club', 'room', 'users'])
            ->withoutByes()
            ->where('start_date_time', '>=', $from)
            ->where('start_date_time', '<', $to)
            ->orderBy('start_date_time')
            ->orderBy('interclubs.id')
            ->get()
            ->sortBy([
                fn (Interclub $a, Interclub $b): int => $a->start_date_time <=> $b->start_date_time,
                fn (Interclub $a, Interclub $b): int => $this->categoryRank($a) <=> $this->categoryRank($b),
                fn (Interclub $a, Interclub $b): int => strnatcasecmp((string) $a->ourTeam()?->name, (string) $b->ourTeam()?->name),
            ])
            ->map(fn (Interclub $interclub): WeeklyFixture => $this->tile($interclub, $this->matchDay($interclub)))
            ->groupBy(fn (WeeklyFixture $fixture): string => $fixture->startsAt->format('Y-m-d'))
            ->map(fn (Collection $day): Collection => $day->values());
    }

    /**
     * The teams on a bye within the window, by category then letter.
     *
     * The window reads the date the import inferred from the rest of the
     * division, so a bye shows the week its round is played, and only then.
     *
     * @return Collection<int, string>
     */
    public function byesBetween(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Interclub::query()
            ->with(['league', 'visitedTeam.club', 'visitingTeam.club'])
            ->where('is_bye', true)
            ->where('start_date_time', '>=', $from)
            ->where('start_date_time', '<', $to)
            ->orderBy('interclubs.id')
            ->get()
            ->sortBy([
                fn (Interclub $a, Interclub $b): int => $this->categoryRank($a) <=> $this->categoryRank($b),
                fn (Interclub $a, Interclub $b): int => strnatcasecmp((string) $a->ourTeam()?->name, (string) $b->ourTeam()?->name),
            ])
            ->map(fn (Interclub $interclub): string => $this->teamName($interclub))
            ->values();
    }

    private function categoryRank(Interclub $interclub): int
    {
        return LeagueCategory::fromName($interclub->league?->category)?->sortOrder() ?? 99;
    }

    private function matchDay(Interclub $interclub): ?int
    {
        if ($interclub->week_number === null) {
            return null;
        }

        $this->matchDayMaps[$interclub->season_id] ??= Interclub::matchDayMap($interclub->season_id);

        return $this->matchDayMaps[$interclub->season_id][$interclub->week_number] ?? null;
    }

    private function teamName(Interclub $interclub): string
    {
        $category = LeagueCategory::fromName($interclub->league?->category);

        return trim(($category?->label() ?? '') . ' ' . ($interclub->ourTeam()?->name ?? ''));
    }

    private function tile(Interclub $interclub, ?int $matchDay): WeeklyFixture
    {
        $category = LeagueCategory::fromName($interclub->league?->category);

        $opponent = $interclub->opponentTeam();
        $isHome = $interclub->isHome();

        $published = $interclub->users
            ->filter(fn (User $user): bool => $user->registration->selection_confirmed_at !== null);

        $players = $interclub->forfeit !== null ? collect() : $published
            ->sortBy(fn (User $user): string => (int) $user->registration->is_walkover . '|' . $user->forceListSortKey($category))
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'last_name' => $user->last_name,
                'first_name' => $user->first_name,
                'ranking' => $user->ranking === Ranking::NA ? null : $user->ranking?->getLabel(),
                'force_list' => $user->forceListFor($category),
                'is_walkover' => (bool) $user->registration->is_walkover,
            ])
            ->values();

        return new WeeklyFixture(
            id: $interclub->id,
            startsAt: $interclub->start_date_time,
            teamName: $this->teamName($interclub),
            opponentName: $opponent?->fullName() ?? '—',
            isHome: $isHome,
            town: $isHome ? null : ($interclub->room?->city_name ?? $opponent?->club?->city_name),
            forfeit: $interclub->forfeit,
            isPublished: $published->isNotEmpty(),
            shortHandedWith: $interclub->isShortHanded() ? $interclub->total_players - 1 : null,
            players: $players->all(),
            matchDay: $matchDay,
        );
    }
}
