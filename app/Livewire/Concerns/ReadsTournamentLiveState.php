<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * What a tournament looks like while it is being played: the tables, what is on
 * them, and the pool standings.
 *
 * Two pages read it. The control room, where the committee runs the day, and
 * the player page, where somebody at the edge of a court looks for their own
 * name. They must not be able to disagree about which match is on which table,
 * so the answer is computed once here rather than twice.
 *
 * The using component must expose a `$tournament` property.
 */
trait ReadsTournamentLiveState
{
    /**
     * The matches being played right now, each with the table it is on.
     *
     * Read off the occupied tables rather than queried again: `tables` already
     * carries the pivot that ties a match to a table.
     *
     * @return Collection<int, array{match: TournamentMatch, table: string, room: string, startedAt: mixed}>
     */
    #[Computed]
    public function liveMatches(): Collection
    {
        return $this->tables
            ->collapse()
            ->reject(fn (array $table): bool => $table['is_free'] || ! $table['match'] instanceof TournamentMatch)
            ->map(fn (array $table): array => [
                'match' => $table['match'],
                'table' => $table['name'],
                'room' => $table['room_name'],
                'startedAt' => $table['match_started_at'],
            ])
            ->values();
    }

    /**
     * Where every player currently on a table is, keyed by their id.
     *
     * The pool standings are the page a player actually reads — their own row
     * and their friends' — so the standings are where "playing now, table 5"
     * belongs. Both members of a pair are listed: a doubles player looking for
     * their partner is looking for a name, not a pair.
     *
     * @return array<int, array{table: string, room: string, startedAt: mixed}>
     */
    #[Computed]
    public function livePlacements(): array
    {
        $placements = [];

        foreach ($this->liveMatches as $live) {
            $match = $live['match'];

            foreach ($match->sidePlayerIds(1)->merge($match->sidePlayerIds(2)) as $playerId) {
                $placements[$playerId] = [
                    'table' => $live['table'],
                    'room' => $live['room'],
                    'startedAt' => $live['startedAt'],
                ];
            }
        }

        return $placements;
    }

    /**
     * @return Collection<int, array{id: int, name: string, finished: bool, players: Collection<int, mixed>, live: Collection<int, mixed>}>
     */
    #[Computed]
    public function pools(): Collection
    {
        $matchService = app(TournamentMatchService::class);
        $live = $this->liveMatches;

        return $this->tournament->pools->map(fn (Pool $pool): array => [
            'id' => $pool->id,
            'name' => $pool->name,
            'finished' => app(TournamentPoolService::class)->isPoolFinished($pool),
            'players' => $matchService->calculatePoolStandings($pool),
            'live' => $live->filter(fn (array $entry): bool => $entry['match']->pool_id === $pool->id)->values(),
        ]);
    }

    /**
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    #[Computed]
    public function tables(): Collection
    {
        return $this->tournament->tables()
            ->with('room')
            ->get()
            ->map(function (Table $table): array {
                $pivot = $table->pivot;
                $match = null;

                if ($pivot->tournament_match_id) {
                    $match = TournamentMatch::with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'sets', 'referee'])
                        ->find($pivot->tournament_match_id);
                }

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'room_name' => $table->room?->name ?? '—',
                    'is_free' => (bool) $pivot->is_table_free,
                    'match' => $match,
                    'match_started_at' => $pivot->match_started_at,
                ];
            })
            ->groupBy('room_name');
    }
}
