<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use App\Data\Interclub\WeeklyFixture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Shared\Enums\LeagueCategory;
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
 */
class WeeklySelections
{
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
            ->map(fn (Interclub $interclub): WeeklyFixture => $this->tile($interclub))
            ->groupBy(fn (WeeklyFixture $fixture): string => $fixture->startsAt->format('Y-m-d'))
            ->map(fn (Collection $day): Collection => $day->values());
    }

    private function categoryRank(Interclub $interclub): int
    {
        return LeagueCategory::fromName($interclub->league?->category)?->sortOrder() ?? 99;
    }

    private function tile(Interclub $interclub): WeeklyFixture
    {
        $category = LeagueCategory::fromName($interclub->league?->category);

        $ours = $interclub->ourTeam();
        $opponent = $interclub->opponentTeam();
        $isHome = $interclub->isHome();

        $published = $interclub->users
            ->filter(fn (User $user): bool => $user->registration->selection_confirmed_at !== null);

        $players = $interclub->forfeit !== null ? collect() : $published
            ->sortBy(fn (User $user): string => sprintf('%d|%04d|%s|%s', (int) $user->registration->is_walkover, $user->forceListFor($category) ?? 9999, $user->last_name, $user->first_name))
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->full_name,
                'is_walkover' => (bool) $user->registration->is_walkover,
            ])
            ->values();

        return new WeeklyFixture(
            id: $interclub->id,
            startsAt: $interclub->start_date_time,
            teamName: trim(($category?->label() ?? '') . ' ' . ($ours?->name ?? '')),
            opponentName: $opponent?->fullName() ?? '—',
            isHome: $isHome,
            town: $isHome ? null : ($interclub->room?->city_name ?? $opponent?->club?->city_name),
            forfeit: $interclub->forfeit,
            isPublished: $published->isNotEmpty(),
            shortHandedWith: $interclub->isShortHanded() ? $interclub->total_players - 1 : null,
            players: $players->all(),
        );
    }
}
