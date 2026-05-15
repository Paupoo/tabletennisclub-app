<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubEvents\Tournament\Pool;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentFinalPhaseService;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;
use App\Services\TournamentTableService;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public Tournament $tournament;

    public string $activeTab = 'pools';

    public bool $scoreDrawer = false;

    public bool $launchDrawer = false;

    public ?int $selectedMatchId = null;

    public ?int $selectedTableId = null;

    /** @var array<int, array{p1: string, p2: string}> */
    public array $setScores = [];

    // ── Computed: phase flags

    #[Computed]
    public function poolsPhaseComplete(): bool
    {
        $poolService = app(TournamentPoolService::class);

        return $this->tournament->pools->every(
            fn (Pool $pool) => $poolService->isPoolFinished($pool)
        );
    }

    #[Computed]
    public function bracketExists(): bool
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->whereNotNull('round')
            ->exists();
    }

    #[Computed]
    public function bracketPhaseComplete(): bool
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('round', 'final')
            ->where('status', 'completed')
            ->exists();
    }

    #[Computed]
    public function tournamentClosed(): bool
    {
        return $this->tournament->status === \App\Enums\TournamentStatusEnum::CLOSED;
    }

    // ── Computed: tab data

    #[Computed]
    public function pools(): Collection
    {
        $matchService = app(TournamentMatchService::class);

        return $this->tournament->pools->map(fn (Pool $pool) => [
            'id'       => $pool->id,
            'name'     => $pool->name,
            'finished' => app(TournamentPoolService::class)->isPoolFinished($pool),
            'players'  => $matchService->calculatePoolStandings($pool),
        ]);
    }

    #[Computed]
    public function tables(): Collection
    {
        return $this->tournament->tables()
            ->with('room')
            ->get()
            ->map(function (Table $table) {
                $pivot = $table->pivot;
                $match = null;

                if ($pivot->tournament_match_id) {
                    $match = TournamentMatch::with(['player1', 'player2', 'sets'])
                        ->find($pivot->tournament_match_id);
                }

                return [
                    'id'               => $table->id,
                    'name'             => $table->name,
                    'room_name'        => $table->room?->name ?? '—',
                    'is_free'          => (bool) $pivot->is_table_free,
                    'match'            => $match,
                    'match_started_at' => $pivot->match_started_at,
                ];
            })
            ->groupBy('room_name');
    }

    #[Computed]
    public function upcomingMatches(): Collection
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('status', 'scheduled')
            ->with(['player1', 'player2', 'pool'])
            ->orderByRaw("CASE WHEN pool_id IS NOT NULL THEN 0 ELSE 1 END")
            ->orderBy('match_order')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function knockoutMatches(): array
    {
        return app(TournamentFinalPhaseService::class)
            ->getKnockoutMatches($this->tournament);
    }

    #[Computed]
    public function selectedMatch(): ?TournamentMatch
    {
        if (! $this->selectedMatchId) {
            return null;
        }

        return TournamentMatch::with(['player1', 'player2', 'sets'])->find($this->selectedMatchId);
    }

    // ── Actions: score entry

    public function openScoreEntry(int $matchId, ?int $tableId = null): void
    {
        $this->selectedMatchId = $matchId;
        $this->selectedTableId = $tableId;
        $maxSets = ($this->tournament->sets_to_win * 2) - 1;
        $this->setScores = array_fill(0, $maxSets, ['p1' => '', 'p2' => '']);
        unset($this->selectedMatch);
        $this->scoreDrawer = true;
    }

    public function submitScore(): void
    {
        $match = TournamentMatch::with(['player1', 'player2'])->find($this->selectedMatchId);

        if (! $match) {
            $this->error(__('Match not found.'));

            return;
        }

        // Build setResults, skipping empty sets
        $setResults = [];
        $p1Sets = 0;
        $p2Sets = 0;

        foreach ($this->setScores as $set) {
            $p1 = (int) ($set['p1'] ?? 0);
            $p2 = (int) ($set['p2'] ?? 0);

            if ($p1 === 0 && $p2 === 0) {
                continue;
            }

            $setResults[] = ['player1_score' => $p1, 'player2_score' => $p2];
            $p1 > $p2 ? $p1Sets++ : $p2Sets++;

            // Stop once a winner is determined
            if ($p1Sets >= $this->tournament->sets_to_win || $p2Sets >= $this->tournament->sets_to_win) {
                break;
            }
        }

        if (empty($setResults)) {
            $this->error(__('Please enter at least one set score.'));

            return;
        }

        if ($p1Sets < $this->tournament->sets_to_win && $p2Sets < $this->tournament->sets_to_win) {
            $this->error(__('Match not finished — a player must win :n sets.', ['n' => $this->tournament->sets_to_win]));

            return;
        }

        $match->recordResult($setResults);

        // Free the table
        app(TournamentTableService::class)->freeUsedTable($match);

        // Progress bracket if knockout match
        if ($match->round !== null && $match->winner_id) {
            app(TournamentFinalPhaseService::class)->completeMatch($match, $match->winner_id);
        }

        $this->scoreDrawer = false;
        $this->selectedMatchId = null;
        unset($this->tables, $this->upcomingMatches, $this->pools, $this->knockoutMatches, $this->selectedMatch);

        $winner = $match->winner_id === $match->player1_id ? $match->player1 : $match->player2;
        $this->success($winner->full_name . ' ' . __('wins!'));
    }

    // ── Actions: launch match

    public function openLaunchDrawer(int $tableId): void
    {
        $this->selectedTableId = $tableId;
        $this->launchDrawer = true;
    }

    public function startMatch(int $matchId): void
    {
        if (! $this->selectedTableId) {
            $this->error(__('No table selected.'));

            return;
        }

        \Illuminate\Support\Facades\DB::table('table_tournament')
            ->where('tournament_id', $this->tournament->id)
            ->where('table_id', $this->selectedTableId)
            ->update([
                'is_table_free'        => false,
                'tournament_match_id'  => $matchId,
                'match_started_at'     => now(),
                'match_ended_at'       => null,
            ]);

        TournamentMatch::where('id', $matchId)->update(['status' => 'in_progress']);

        $this->launchDrawer = false;
        $this->selectedTableId = null;
        unset($this->tables, $this->upcomingMatches);

        $this->success(__('Match started!'));
    }

    // ── Actions: bracket

    public function generateBracket(): void
    {
        if (! $this->poolsPhaseComplete) {
            $this->error(__('All pool matches must be completed before creating the bracket.'));

            return;
        }

        // Determine starting round based on total qualifiers
        $totalQualifiers = $this->tournament->nb_pools * $this->tournament->nb_qualifiers_per_pool;
        $startingRound = match (true) {
            $totalQualifiers >= 9  => 'round_16',
            $totalQualifiers >= 5  => 'quarterfinal',
            default                => 'semifinal',
        };

        try {
            app(TournamentFinalPhaseService::class)
                ->configureKnockoutPhase($this->tournament, $startingRound);
        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return;
        }

        unset($this->knockoutMatches, $this->bracketExists, $this->bracketPhaseComplete);
        $this->activeTab = 'bracket';
        $this->success(__('Bracket created!'));
    }

    public function closeTournament(): void
    {
        $this->tournament->update(['status' => \App\Enums\TournamentStatusEnum::CLOSED]);
        unset($this->tournamentClosed);
        $this->success(__('Tournament closed. Congratulations to all participants!'));
    }

    // ── Render

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->tournaments()
                ->current('Live Center — ' . $this->tournament->name)
                ->toArray(),
        ];
    }

    public function render(): View
    {
        return view('pages.club-events.tournaments.⚡live-center.live-center');
    }
};
