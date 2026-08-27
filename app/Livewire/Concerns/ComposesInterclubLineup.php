<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Competitions\Interclub\Services\InterclubPreparationService;
use App\Domains\Shared\Enums\InterclubAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Composing a lineup, for every screen that composes one.
 *
 * The selections screen and the control center each had their own version of
 * this, and the control center's was quietly the poorer of the two: it showed
 * neither availabilities nor the players already lined up elsewhere that week,
 * so an admin composing from there was blind to the two things that decide a
 * lineup. One implementation now, and the drawer they share renders it.
 */
trait ComposesInterclubLineup
{
    /**
     * Players already lined up the same week *in the same category*, and by whom.
     *
     * Same rule as isPlayerDoubleBooked(): a ladies fixture never blocks a senior
     * one. This deliberately keeps its own query — a plain captain only ever
     * loads their own fixtures, and the whole point is to catch a player lined up
     * by somebody else.
     *
     * @return array<int, string> user_id => team name
     */
    protected function blockedPlayerData(Interclub $interclub): array
    {
        $blocked = [];

        $sameWeekMatches = Interclub::where('season_id', $interclub->season_id)
            ->where('week_number', $interclub->week_number)
            ->where('id', '!=', $interclub->id)
            ->whereHas('league', $this->sameCategoryAs($interclub))
            ->with([
                'visitedTeam.club',
                'visitingTeam.club',
                'users' => fn ($q) => $q->wherePivot('is_selected', true),
            ])
            ->get();

        foreach ($sameWeekMatches as $match) {
            $team = $match->visitedTeam?->club?->is_own_club
                ? $match->visitedTeam
                : $match->visitingTeam;

            foreach ($match->users as $user) {
                $blocked[$user->id] = $team?->name ?? '?';
            }
        }

        return $blocked;
    }

    /**
     * Everyone the drawer offers: the team's own players, plus any substitute
     * already picked from outside it.
     *
     * @param  array<int, int>  $selectedPlayerIds
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildLineupRoster(
        Interclub $interclub,
        ?Team $team,
        ?Season $season,
        EloquentCollection $fixtures,
        array $selectedPlayerIds,
    ): Collection {
        $pivotMap = $interclub->users->keyBy('id')->map(fn ($u) => $u->registration);
        $blockedPlayerData = $this->blockedPlayerData($interclub);

        $roster = ($team?->users ?? collect())
            ->map(fn (User $player): array => $this->buildLineupPlayer($player, $pivotMap, $team, $season, $fixtures, $blockedPlayerData))
            ->sortBy([
                ['rank_sort', 'asc'],
                ['last_name', 'asc'],
                ['first_name', 'asc'],
            ])
            ->values();

        $teamUserIds = $team?->users->pluck('id')->toArray() ?? [];
        $substituteIds = array_diff($selectedPlayerIds, $teamUserIds);

        if ($substituteIds !== []) {
            $substitutes = User::whereIn('id', $substituteIds)->get()
                ->map(fn (User $player): array => $this->buildLineupPlayer($player, $pivotMap, $team, $season, $fixtures, $blockedPlayerData))
                ->values();

            $roster = $roster->concat($substitutes)->values();
        }

        return $roster;
    }

    /**
     * At most one fixture per category per week, per player.
     *
     * The rule used to be "one fixture per week", full stop. That is right for
     * two senior teams, and harmless between seniors and veterans — veterans
     * play during the seniors' rest weeks, so the case never arises. It is wrong
     * for the ladies, who play alongside the seniors: a woman may be lined up on
     * Friday with the ladies and on Saturday with a senior team. The old rule
     * refused her on the eighteen weeks where both calendars meet.
     */
    protected function isPlayerDoubleBooked(int $userId, Interclub $interclub): bool
    {
        return Interclub::where('season_id', $interclub->season_id)
            ->where('week_number', $interclub->week_number)
            ->where('id', '!=', $interclub->id)
            ->whereHas('league', $this->sameCategoryAs($interclub))
            ->whereHas('users', fn ($q) => $q
                ->where('users.id', $userId)
                ->where('interclub_user.is_selected', 1))
            ->exists();
    }

    /**
     * @param  Collection<int, mixed>  $pivotMap
     * @param  EloquentCollection<int, Interclub>  $fixtures
     * @param  array<int, string>  $blockedPlayerData
     * @return array<string, mixed>
     */
    private function buildLineupPlayer(
        User $player,
        Collection $pivotMap,
        ?Team $team,
        ?Season $season,
        EloquentCollection $fixtures,
        array $blockedPlayerData,
    ): array {
        $pivot = $pivotMap->get($player->id);
        $availability = $pivot?->availability
            ? InterclubAvailability::from($pivot->availability)
            : null;

        return [
            'id' => $player->id,
            'name' => $player->last_name . ' ' . $player->first_name,
            'last_name' => $player->last_name ?? '',
            'first_name' => $player->first_name ?? '',
            // Captain override (decision T8): a captain always sees their own
            // players' contact details on the selection screen, regardless of
            // the members' opt-in contact-visibility preferences.
            'phone_number' => $player->phone_number,
            'email' => $player->email,
            'rank' => $player->ranking ?? '—',
            'rank_sort' => $player->ranking ?? 'ZZZ',
            'availability' => $availability,
            'availability_note' => $pivot?->availability_note,
            'matches_played' => $season && $team
                ? $this->countFixtures($player->id, $team->id, $season, $fixtures, 'has_played')
                : 0,
            'matches_selected' => $season && $team
                ? $this->countFixtures($player->id, $team->id, $season, $fixtures, 'is_selected')
                : 0,
            'is_blocked' => isset($blockedPlayerData[$player->id]),
            'blocked_team' => $blockedPlayerData[$player->id] ?? null,
        ];
    }

    /**
     * Played counts only fixtures behind us; selected counts the whole season.
     *
     * @param  EloquentCollection<int, Interclub>  $fixtures
     */
    private function countFixtures(int $userId, int $teamId, Season $season, EloquentCollection $fixtures, string $flag): int
    {
        return app(InterclubPreparationService::class)
            ->fixturesForTeam($fixtures, $teamId)
            ->filter(fn (Interclub $ic): bool => $ic->season_id === $season->id
                && ($flag !== 'has_played' || $ic->start_date_time < now())
                && $ic->users->contains(fn (User $u): bool => $u->id === $userId && (bool) $u->registration?->{$flag}))
            ->count();
    }

    /**
     * Constrains a league query to the category of the given fixture. A fixture
     * without a category only ever clashes with another one without a category.
     *
     * @return \Closure(Builder<League>): void
     */
    private function sameCategoryAs(Interclub $interclub): \Closure
    {
        // Chargement explicite : le lazy loading est désactivé, et l'appelant
        // n'a pas toujours la relation en main.
        $interclub->loadMissing('league');
        $category = $interclub->league?->category;

        return $category === null
            ? fn ($q) => $q->whereNull('category')
            : fn ($q) => $q->where('category', $category);
    }
}
