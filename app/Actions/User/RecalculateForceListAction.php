<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Gender;
use Illuminate\Support\Collection;

class RecalculateForceListAction
{
    /**
     * Recalculates the three category force lists for every competitor.
     *
     * The force index is a *block* index per ranking, not a unique per-player
     * position: every competitor sharing a ranking shares the same index, which
     * equals the cumulative number of competitors of that ranking or stronger.
     * E6 and NC are merged into a single (weakest) block; NA is out of scope and
     * keeps a null index. Ties are only broken by name at display time.
     *
     * The index is computed independently within each category population:
     *   - force_list           → all competitors
     *   - force_list_women      → women only
     *   - force_list_veterans   → veterans (>= User::VETERAN_AGE at season end)
     *
     * Uses updateQuietly to avoid re-triggering the observer.
     */
    public static function handle(): void
    {
        $season = Season::current();

        $competitors = User::competitor()->get();

        $general = self::blockIndexByUser($competitors);
        $women = self::blockIndexByUser($competitors->filter(fn (User $user) => $user->gender === Gender::WOMEN));
        $veterans = self::blockIndexByUser($competitors->filter(fn (User $user) => $user->isVeteran($season)));

        foreach ($competitors as $competitor) {
            $competitor->updateQuietly([
                'force_list' => $general[$competitor->id] ?? null,
                'force_list_women' => $women[$competitor->id] ?? null,
                'force_list_veterans' => $veterans[$competitor->id] ?? null,
            ]);
        }
    }

    /**
     * Block force index keyed by user id for a given competitor population:
     * competitors grouped by ranking (E6 + NC merged, NA excluded), ordered
     * strongest → weakest, each block carrying the cumulative headcount up to
     * and including itself.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, int>
     */
    private static function blockIndexByUser(Collection $users): array
    {
        $groups = $users
            ->reject(fn (User $user) => $user->ranking === 'NA')
            ->groupBy(fn (User $user) => in_array($user->ranking, ['E6', 'NC'], true) ? 'E6-NC' : $user->ranking)
            ->sortKeys();

        $index = [];
        $cumulative = 0;

        foreach ($groups as $group) {
            $cumulative += $group->count();

            foreach ($group as $user) {
                $index[$user->id] = $cumulative;
            }
        }

        return $index;
    }
}
