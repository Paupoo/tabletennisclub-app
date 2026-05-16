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

    public int $p1Handicap = 0;

    public int $p2Handicap = 0;

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
            ->orderByRaw("CASE WHEN player1_id IS NOT NULL AND player2_id IS NOT NULL THEN 0 ELSE 1 END")
            ->orderByRaw("CASE round WHEN 'round_16' THEN 1 WHEN 'quarterfinal' THEN 2 WHEN 'semifinal' THEN 3 WHEN 'final' THEN 4 WHEN 'bronze' THEN 5 ELSE 0 END")
            ->orderBy('match_order')
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function rankings(): Collection
    {
        /** @var array<int, array{user: mixed, rank: int, result: string}> */
        $ranked = [];

        $bracketMatches = TournamentMatch::where('tournament_id', $this->tournament->id)
            ->whereNotNull('round')
            ->where('status', 'completed')
            ->with(['player1', 'player2'])
            ->get();

        $place = function (TournamentMatch $match, int $winnerRank, int $loserRank, string $winnerLabel, string $loserLabel) use (&$ranked): void {
            if (! $match->winner_id) {
                return;
            }
            $isP1 = $match->winner_id === $match->player1_id;
            $wid  = $match->winner_id;
            $lid  = $isP1 ? $match->player2_id : $match->player1_id;
            $wu   = $isP1 ? $match->player1 : $match->player2;
            $lu   = $isP1 ? $match->player2 : $match->player1;

            $ranked[$wid] = ['user' => $wu, 'rank' => $winnerRank, 'result' => $winnerLabel];
            $ranked[$lid] = ['user' => $lu, 'rank' => $loserRank,  'result' => $loserLabel];
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
                $lid  = $isP1 ? $match->player2_id : $match->player1_id;
                $lu   = $isP1 ? $match->player2 : $match->player1;
                if (! isset($ranked[$lid])) {
                    $ranked[$lid] = ['user' => $lu, 'rank' => $pos++, 'result' => __($label)];
                }
            }
        }

        $matchService = app(TournamentMatchService::class);
        $nextRank     = empty($ranked) ? 1 : collect($ranked)->max('rank') + 1;

        foreach ($this->tournament->pools as $pool) {
            foreach ($matchService->calculatePoolStandings($pool) as $standing) {
                $pid = $standing['player']->id;
                if (! isset($ranked[$pid])) {
                    $ranked[$pid] = ['user' => $standing['player'], 'rank' => $nextRank++, 'result' => $pool->name];
                }
            }
        }

        return collect($ranked)->sortBy('rank')->values();
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

        $match = TournamentMatch::with('sets')->find($matchId);

        $this->p1Handicap = ($this->tournament->has_handicap_points && $match) ? $match->player1_handicap_points : 0;
        $this->p2Handicap = ($this->tournament->has_handicap_points && $match) ? $match->player2_handicap_points : 0;

        $this->setScores = array_fill(0, $maxSets, ['p1' => (string) $this->p1Handicap, 'p2' => (string) $this->p2Handicap]);

        // Pre-load any previously saved sets
        if ($match) {
            foreach ($match->sets as $set) {
                $idx = $set->set_number - 1;
                if (isset($this->setScores[$idx])) {
                    $this->setScores[$idx] = ['p1' => (string) $set->player1_score, 'p2' => (string) $set->player2_score];
                }
            }
        }

        unset($this->selectedMatch);
        $this->scoreDrawer = true;
    }

    /**
     * Parse current setScores into valid set results, stopping once a winner is determined.
     * Empty detection uses handicap starting values (Approach B).
     *
     * @return array{results: array<int, array{player1_score: int, player2_score: int}>, p1Sets: int, p2Sets: int}
     */
    private function parseSetResults(): array
    {
        $results = [];
        $p1Sets = 0;
        $p2Sets = 0;

        foreach ($this->setScores as $set) {
            $p1 = (int) ($set['p1'] ?? 0);
            $p2 = (int) ($set['p2'] ?? 0);

            if ($p1 === $this->p1Handicap && $p2 === $this->p2Handicap) {
                continue;
            }

            $results[] = ['player1_score' => $p1, 'player2_score' => $p2];
            $p1 > $p2 ? $p1Sets++ : $p2Sets++;

            if ($p1Sets >= $this->tournament->sets_to_win || $p2Sets >= $this->tournament->sets_to_win) {
                break;
            }
        }

        return compact('results', 'p1Sets', 'p2Sets');
    }

    public function saveDraft(): void
    {
        $match = TournamentMatch::find($this->selectedMatchId);

        if (! $match) {
            return;
        }

        ['results' => $setResults] = $this->parseSetResults();

        if (empty($setResults)) {
            $this->error(__('No set scores to save.'));

            return;
        }

        $match->saveDraft($setResults);
        $this->scoreDrawer = false;
        $this->selectedMatchId = null;
        unset($this->selectedMatch);
        $this->success(__('Sets saved.'));
    }

    public function submitScore(): void
    {
        $match = TournamentMatch::with(['player1', 'player2'])->find($this->selectedMatchId);

        if (! $match) {
            $this->error(__('Match not found.'));

            return;
        }

        ['results' => $setResults, 'p1Sets' => $p1Sets, 'p2Sets' => $p2Sets] = $this->parseSetResults();

        if (empty($setResults)) {
            $this->error(__('Please enter at least one set score.'));

            return;
        }

        if ($p1Sets < $this->tournament->sets_to_win && $p2Sets < $this->tournament->sets_to_win) {
            $this->error(__('Match not finished — a player must win :n sets.', ['n' => $this->tournament->sets_to_win]));

            return;
        }

        foreach ($setResults as $i => $set) {
            $error = $this->tournament->validateSetScore($set['player1_score'], $set['player2_score'], $i + 1, $this->p1Handicap, $this->p2Handicap);
            if ($error) {
                $this->error($error);

                return;
            }
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
