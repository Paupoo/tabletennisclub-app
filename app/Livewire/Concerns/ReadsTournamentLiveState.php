<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Services\TournamentFinalPhaseService;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * What a tournament looks like while it is being played: the tables, what is on
 * them, the pool standings, the bracket and the rankings.
 *
 * Two pages read it. The control room, where the committee runs the day, and
 * the player page, where somebody at the edge of a court looks for their own
 * name. The two show the same tournament and must not be able to disagree
 * about it, so each answer is computed once here rather than twice.
 *
 * The using component must expose a `$tournament` property.
 */
trait ReadsTournamentLiveState
{
    #[Computed]
    public function bracketExists(): bool
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->whereNotNull('round')
            ->exists();
    }

    #[Computed]
    public function knockoutMatches(): array
    {
        return app(TournamentFinalPhaseService::class)
            ->getKnockoutMatches($this->tournament);
    }

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
     * and their friends' — so a table number next to a name is all the
     * standings need to say. Both members of a pair are listed: a doubles
     * player looking for their partner is looking for a name, not a pair.
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
     * @return Collection<int, array{id: int, name: string, finished: bool, players: Collection<int, mixed>}>
     */
    #[Computed]
    public function pools(): Collection
    {
        $matchService = app(TournamentMatchService::class);

        return $this->tournament->pools->map(fn (Pool $pool): array => [
            'id' => $pool->id,
            'name' => $pool->name,
            'finished' => app(TournamentPoolService::class)->isPoolFinished($pool),
            'players' => $matchService->calculatePoolStandings($pool),
        ]);
    }

    #[Computed]
    public function rankings(): Collection
    {
        /** @var array<int, array{user: mixed, rank: int, result: string}> */
        $ranked = [];

        $bracketMatches = TournamentMatch::where('tournament_id', $this->tournament->id)
            ->whereNotNull('round')
            ->where('status', 'completed')
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2'])
            ->get();

        $place = function (TournamentMatch $match, int $winnerRank, int $loserRank, string $winnerLabel, string $loserLabel) use (&$ranked): void {
            if (! $match->winner_id) {
                return;
            }
            $isP1 = $match->winner_id === $match->player1_id;
            $wid = $match->winner_id;
            $lid = $isP1 ? $match->player2_id : $match->player1_id;
            $wu = $isP1 ? $match->player1 : $match->player2;
            $lu = $isP1 ? $match->player2 : $match->player1;
            $wp = $isP1 ? $match->pair1 : $match->pair2;
            $lp = $isP1 ? $match->pair2 : $match->pair1;

            $ranked[$wid] = ['user' => $wu, 'pair' => $wp, 'rank' => $winnerRank, 'result' => $winnerLabel];
            $ranked[$lid] = ['user' => $lu, 'pair' => $lp, 'rank' => $loserRank,  'result' => $loserLabel];
        };

        if ($final = $bracketMatches->firstWhere('round', 'final')) {
            $place($final, 1, 2, __('Champion'), __('Runner-up'));
        }

        if ($bronze = $bracketMatches->firstWhere('round', 'bronze')) {
            $place($bronze, 3, 4, __('3rd place'), __('4th place'));
        }

        foreach (['quarterfinal' => [5, 'Quarterfinalist'], 'round_16' => [9, 'Round of 16']] as $round => [$startRank, $label]) {
            $pos = $startRank;
            foreach ($bracketMatches->where('round', $round) as $match) {
                if (! $match->winner_id) {
                    continue;
                }
                $isP1 = $match->winner_id === $match->player1_id;
                $lid = $isP1 ? $match->player2_id : $match->player1_id;
                $lu = $isP1 ? $match->player2 : $match->player1;
                $lp = $isP1 ? $match->pair2 : $match->pair1;
                if (! isset($ranked[$lid])) {
                    $ranked[$lid] = ['user' => $lu, 'pair' => $lp, 'rank' => $pos++, 'result' => __($label)];
                }
            }
        }

        $matchService = app(TournamentMatchService::class);
        $nextRank = $ranked === [] ? 1 : collect($ranked)->max('rank') + 1;

        foreach ($this->tournament->pools as $pool) {
            foreach ($matchService->calculatePoolStandings($pool) as $standing) {
                $pid = $standing['player']->id;
                if (! isset($ranked[$pid])) {
                    $ranked[$pid] = [
                        'user' => $standing['player'],
                        'pair' => $standing['pair'] ?? null,
                        'rank' => $nextRank++,
                        'result' => $pool->name,
                    ];
                }
            }
        }

        return collect($ranked)->sortBy('rank')->values();
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
