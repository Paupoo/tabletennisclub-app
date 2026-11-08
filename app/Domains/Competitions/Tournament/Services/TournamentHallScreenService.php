<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;

/**
 * What the television in the hall shows: the tables, what comes next, and one
 * pool at a time.
 *
 * Read from across the room in a few seconds: full names, table numbers, no
 * score detail. The question it answers first is « where do I play? ».
 */
final readonly class TournamentHallScreenService
{
    private const int QUEUE_LENGTH = 5;

    public function __construct(private TournamentMatchService $matches) {}

    /**
     * @return array{tournament: Tournament, facts: string, tables: list<array{name: string, match: string|null}>, queue: list<array{match: string, where: string}>, pools: list<array{name: string, rows: list<array{name: string, played: int, won: int}>}>}
     */
    public function for(Tournament $tournament): array
    {
        $poolMatches = TournamentMatch::where('tournament_id', $tournament->id)->whereNotNull('pool_id');

        return [
            'tournament' => $tournament,
            'facts' => __('Pools: :played matches out of :total', [
                'played' => (clone $poolMatches)->where('status', 'completed')->count(),
                'total' => $poolMatches->count(),
            ]),
            'tables' => $this->tables($tournament),
            'queue' => $this->queue($tournament),
            'pools' => $tournament->pools()->with(['users', 'pairs.player1', 'pairs.player2'])->orderBy('name')->get()
                ->map(fn (Pool $pool): array => [
                    'name' => __('Pool :name', ['name' => $pool->name]),
                    'rows' => $this->matches->calculatePoolStandings($pool)
                        ->map(fn (array $entry): array => [
                            'name' => isset($entry['pair']) ? $entry['pair']->displayName() : ($entry['player']->full_name ?? '—'),
                            'played' => (int) ($entry['matches_played'] ?? 0),
                            'won' => (int) ($entry['matches_won'] ?? 0),
                        ])->values()->all(),
                ])->all(),
        ];
    }

    private function label(TournamentMatch $match): string
    {
        $side = fn (int $side): string => $match->pair1_id !== null
            ? ($side === 1 ? $match->pair1?->displayName() : $match->pair2?->displayName()) ?? '—'
            : ($side === 1 ? $match->player1?->full_name : $match->player2?->full_name) ?? '—';

        return __(':first against :second', ['first' => $side(1), 'second' => $side(2)]);
    }

    /**
     * The next matches, in the order the control room offers them.
     *
     * @return list<array{match: string, where: string}>
     */
    private function queue(Tournament $tournament): array
    {
        return TournamentMatch::where('tournament_id', $tournament->id)
            ->where('status', 'scheduled')
            ->whereNotNull('player1_id')
            ->whereNotNull('player2_id')
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'pool'])
            ->orderByRaw('CASE WHEN pool_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE round WHEN 'round_16' THEN 1 WHEN 'quarterfinal' THEN 2 WHEN 'semifinal' THEN 3 WHEN 'final' THEN 4 WHEN 'bronze' THEN 5 ELSE 0 END")
            ->orderBy('match_order')
            ->limit(self::QUEUE_LENGTH)
            ->get()
            ->map(fn (TournamentMatch $match): array => [
                'match' => $this->label($match),
                'where' => $match->pool ? __('Pool :name', ['name' => $match->pool->name]) : __('Bracket'),
            ])->all();
    }

    /**
     * @return list<array{name: string, match: string|null}>
     */
    private function tables(Tournament $tournament): array
    {
        return $tournament->tables()->orderBy('name')->get()
            ->map(function (Table $table): array {
                $match = $table->pivot->tournament_match_id
                    ? TournamentMatch::with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2'])->find($table->pivot->tournament_match_id)
                    : null;

                return ['name' => $table->name, 'match' => $match instanceof TournamentMatch ? $this->label($match) : null];
            })->values()->all();
    }
}
