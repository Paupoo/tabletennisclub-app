<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Mail\TournamentResultsMail;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Pool;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Services\TournamentFinalPhaseService;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;
use App\Services\TournamentTableService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, WithFileUploads, HasBreadcrumbs;

    public Tournament $tournament;

    public string $activeTab = 'pools';

    // ── Score entry drawer

    public bool $scoreDrawer = false;

    public bool $launchDrawer = false;

    public ?int $selectedMatchId = null;

    public ?int $selectedTableId = null;

    public int $p1Handicap = 0;

    public int $p2Handicap = 0;

    /** @var array<int, array{p1: string, p2: string}> */
    public array $setScores = [];

    public bool $scoresDirty = false;

    // ── Closure tab fields

    public bool $sendThankYou = true;

    public string $thankYouSubject = '';

    public string $thankYouBody = '';

    public bool $createNewsPost = false;

    public string $newsPostTitle = '';

    public string $newsPostContent = '';

    public mixed $newsPostImage = null;

    public function mount(): void
    {
        $this->thankYouSubject  = __('Results') . ' — ' . $this->tournament->name;
        $this->thankYouBody     = __('Dear participants,') . "\n\n"
            . __('Thank you for joining us for :name! It was a great day of table tennis.', ['name' => $this->tournament->name]) . "\n\n"
            . __('See you at the next tournament!');

        $this->newsPostTitle   = __('Results') . ' — ' . $this->tournament->name;
        $this->newsPostContent = "## " . $this->tournament->name . "\n\n"
            . "**" . __('Podium') . " :**\n\n"
            . "1. \n2. \n3. \n\n"
            . __('Congratulations to all participants!');
    }

    public function fillClosureFromRankings(): void
    {
        abort_unless($this->canManageTournament, 403);

        $top3 = $this->rankings->take(3);

        if ($top3->isEmpty()) {
            $this->error(__('No rankings available yet.'));

            return;
        }

        $podiumLines = $top3->map(fn ($e) => $e['rank'] . '. ' . $e['user']->full_name . ' (' . $e['result'] . ')')->implode("\n");

        $this->thankYouBody = __('Dear participants,') . "\n\n"
            . __('Thank you for joining us for :name! It was a great day of table tennis.', ['name' => $this->tournament->name]) . "\n\n"
            . __('Final podium:') . "\n" . $podiumLines . "\n\n"
            . __('See you at the next tournament!');

        $this->newsPostContent = "## " . $this->tournament->name . "\n\n"
            . "**" . __('Podium') . " :**\n\n"
            . $top3->map(fn ($e) => '- **' . $e['rank'] . '. ' . $e['user']->full_name . '** — ' . $e['result'])->implode("\n")
            . "\n\n" . __('Congratulations to all participants!');
    }

    // ── Lifecycle hooks

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'setScores')) {
            $this->scoresDirty = true;
        }
    }

    public function rendering(): void
    {
        if ($this->scoreDrawer && $this->selectedMatchId && ! $this->scoresDirty) {
            $this->syncSetScoresFromDb();
        }
    }

    private function syncSetScoresFromDb(): void
    {
        $match = TournamentMatch::with('sets')->find($this->selectedMatchId);

        if (! $match || $match->sets->isEmpty()) {
            return;
        }

        foreach ($match->sets as $set) {
            $idx = $set->set_number - 1;
            if (array_key_exists($idx, $this->setScores)) {
                $this->setScores[$idx] = [
                    'p1' => (string) $set->player1_score,
                    'p2' => (string) $set->player2_score,
                ];
            }
        }
    }

    // ── Computed: authorization

    #[Computed]
    public function canManageTournament(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ($user->is_admin || $user->is_committee_member);
    }

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
    public function allMatchesComplete(): bool
    {
        return ! TournamentMatch::where('tournament_id', $this->tournament->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();
    }

    #[Computed]
    public function tournamentClosed(): bool
    {
        return $this->tournament->status === TournamentStatusEnum::CLOSED;
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
                    $match = TournamentMatch::with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'sets', 'referee'])
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

    /** @return array<int> */
    #[Computed]
    public function busyPlayerIds(): array
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('status', 'in_progress')
            ->with(['pair1', 'pair2'])
            ->get()
            ->flatMap(fn (TournamentMatch $m) => [
                $m->player1_id,
                $m->player2_id,
                $m->pair1?->player1_id,
                $m->pair1?->player2_id,
                $m->pair2?->player1_id,
                $m->pair2?->player2_id,
                $m->referee_id,
            ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    #[Computed]
    public function upcomingMatches(): Collection
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('status', 'scheduled')
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'pool', 'referee'])
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
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2'])
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
            $wp   = $isP1 ? $match->pair1 : $match->pair2;
            $lp   = $isP1 ? $match->pair2 : $match->pair1;

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
                $lid  = $isP1 ? $match->player2_id : $match->player1_id;
                $lu   = $isP1 ? $match->player2 : $match->player1;
                $lp   = $isP1 ? $match->pair2 : $match->pair1;
                if (! isset($ranked[$lid])) {
                    $ranked[$lid] = ['user' => $lu, 'pair' => $lp, 'rank' => $pos++, 'result' => __($label)];
                }
            }
        }

        $matchService = app(TournamentMatchService::class);
        $nextRank     = empty($ranked) ? 1 : collect($ranked)->max('rank') + 1;

        foreach ($this->tournament->pools as $pool) {
            foreach ($matchService->calculatePoolStandings($pool) as $standing) {
                $pid = $standing['player']->id;
                if (! isset($ranked[$pid])) {
                    $ranked[$pid] = [
                        'user'   => $standing['player'],
                        'pair'   => $standing['pair'] ?? null,
                        'rank'   => $nextRank++,
                        'result' => $pool->name,
                    ];
                }
            }
        }

        return collect($ranked)->sortBy('rank')->values();
    }

    #[Computed]
    public function unpaidParticipants(): Collection
    {
        if (! $this->tournament->isPaid()) {
            return collect();
        }

        $users = $this->tournament->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->wherePivot('has_paid', false)
            ->get();

        $paymentIds = $users->map(fn ($u) => $u->pivot->payment_id)->filter()->unique()->values();
        $paidIds = Payment::whereIn('id', $paymentIds)->where('status', 'paid')->pluck('id')->flip();

        return $users
            ->filter(fn ($u) => ! isset($paidIds[$u->pivot->payment_id]))
            ->map(fn ($u) => [
                'user'         => $u,
                'qr_confirmed' => (bool) $u->pivot->qr_confirmed,
            ])
            ->values();
    }

    #[Computed]
    public function newsPostMarkdownPreview(): string
    {
        return Str::markdown($this->newsPostContent ?: '');
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

        return TournamentMatch::with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'sets'])->find($this->selectedMatchId);
    }

    // ── Actions: score entry

    public function openScoreEntry(int $matchId, ?int $tableId = null): void
    {
        abort_unless($this->canManageTournament, 403);

        $this->selectedMatchId = $matchId;
        $this->selectedTableId = $tableId;
        $maxSets = ($this->tournament->sets_to_win * 2) - 1;

        $match = TournamentMatch::with('sets')->find($matchId);

        $this->p1Handicap = ($this->tournament->has_handicap_points && $match) ? $match->player1_handicap_points : 0;
        $this->p2Handicap = ($this->tournament->has_handicap_points && $match) ? $match->player2_handicap_points : 0;

        $this->setScores = array_fill(0, $maxSets, ['p1' => (string) $this->p1Handicap, 'p2' => (string) $this->p2Handicap]);

        if ($match) {
            foreach ($match->sets as $set) {
                $idx = $set->set_number - 1;
                if (isset($this->setScores[$idx])) {
                    $this->setScores[$idx] = ['p1' => (string) $set->player1_score, 'p2' => (string) $set->player2_score];
                }
            }
        }

        unset($this->selectedMatch);
        $this->scoresDirty = false;
        $this->scoreDrawer = true;
    }

    /**
     * @return array{results: array<int, array{player1_score: int, player2_score: int}>, p1Sets: int, p2Sets: int}
     */
    private function parseSetResults(): array
    {
        $results = [];
        $p1Sets  = 0;
        $p2Sets  = 0;

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
        abort_unless($this->canManageTournament, 403);

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
        $this->scoreDrawer    = false;
        $this->selectedMatchId = null;
        unset($this->selectedMatch);
        $this->success(__('Sets saved.'));
    }

    public function submitScore(): void
    {
        abort_unless($this->canManageTournament, 403);

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

        app(TournamentTableService::class)->freeUsedTable($match);

        if ($match->round !== null && $match->winner_id) {
            app(TournamentFinalPhaseService::class)->completeMatch($match, $match->winner_id);
        }

        $this->scoreDrawer    = false;
        $this->selectedMatchId = null;
        unset($this->tables, $this->upcomingMatches, $this->pools, $this->knockoutMatches, $this->selectedMatch);

        $match->loadMissing(['pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2']);
        $isDoubles   = $match->pair1_id !== null;
        $winnerName  = $isDoubles
            ? ($match->winner_id === $match->player1_id ? $match->pair1?->displayName() : $match->pair2?->displayName())
            : ($match->winner_id === $match->player1_id ? $match->player1?->full_name : $match->player2?->full_name);
        $this->success(($winnerName ?? '—') . ' ' . __('wins!'));
    }

    // ── Actions: launch match

    public function openLaunchDrawer(int $tableId): void
    {
        abort_unless($this->canManageTournament, 403);

        $this->selectedTableId = $tableId;
        $this->launchDrawer    = true;
    }

    public function startMatch(int $matchId): void
    {
        abort_unless($this->canManageTournament, 403);

        if (! $this->selectedTableId) {
            $this->error(__('No table selected.'));

            return;
        }

        $match = TournamentMatch::with(['pair1', 'pair2'])->findOrFail($matchId);

        $conflict = app(TournamentMatchService::class)->detectStartConflict($this->tournament, $match);
        if ($conflict !== null) {
            $this->error($conflict);

            return;
        }

        \Illuminate\Support\Facades\DB::table('table_tournament')
            ->where('tournament_id', $this->tournament->id)
            ->where('table_id', $this->selectedTableId)
            ->update([
                'is_table_free'       => false,
                'tournament_match_id' => $matchId,
                'match_started_at'    => now(),
                'match_ended_at'      => null,
            ]);

        TournamentMatch::where('id', $matchId)->update(['status' => 'in_progress']);

        $this->launchDrawer    = false;
        $this->selectedTableId = null;
        unset($this->tables, $this->upcomingMatches);

        $this->success(__('Match started!'));
    }


    // ── Actions: bracket

    public function generateBracket(): void
    {
        abort_unless($this->canManageTournament, 403);

        if (! $this->poolsPhaseComplete) {
            $this->error(__('All pool matches must be completed before creating the bracket.'));

            return;
        }

        $totalQualifiers = $this->tournament->nb_pools * $this->tournament->nb_qualifiers_per_pool;
        $startingRound   = match (true) {
            $totalQualifiers >= 9 => 'round_16',
            $totalQualifiers >= 5 => 'round_8',
            default               => 'round_4',
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

    // ── Actions: closure

    public function removeNewsPostImage(): void
    {
        $this->newsPostImage = null;
    }

    public function closeTournament(): void
    {
        abort_unless($this->canManageTournament, 403);

        if (! $this->allMatchesComplete) {
            $this->error(__('All matches must be completed before closing the tournament.'));

            return;
        }

        $this->validate([
            'newsPostImage' => ['nullable', 'image', 'max:4096'],
        ]);

        $this->tournament->update(['status' => TournamentStatusEnum::CLOSED]);

        if ($this->sendThankYou && $this->thankYouSubject !== '' && $this->thankYouBody !== '') {
            $rankings = $this->rankings;

            $this->tournament->users()
                ->wherePivotIn('registration_status', ['registered', 'confirmed'])
                ->get()
                ->each(function (User $user) use ($rankings): void {
                    Mail::to($user->email)->queue(new TournamentResultsMail(
                        tournament: $this->tournament,
                        recipient: $user,
                        emailSubject: $this->thankYouSubject,
                        emailBody: $this->thankYouBody,
                        rankings: $rankings,
                    ));
                });
        }

        if ($this->createNewsPost && $this->newsPostTitle !== '' && $this->newsPostContent !== '') {
            $imagePath = null;

            if ($this->newsPostImage) {
                $imagePath = $this->newsPostImage->store('clubPosts', 'public');
            }

            $newsPost = NewsPost::create([
                'title'    => $this->newsPostTitle,
                'slug'     => Str::slug($this->newsPostTitle . '-' . now()->year),
                'content'  => $this->newsPostContent,
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status'   => NewsPostStatusEnum::PUBLISHED,
                'is_public' => false,
                'image'    => $imagePath,
                'user_id'  => auth()->id(),
            ]);

            $this->tournament->update(['news_post_id' => $newsPost->id]);
        }

        unset($this->tournamentClosed, $this->allMatchesComplete);
        $this->success(__('Tournament closed. Congratulations to all participants!'));
    }

    // ── Render

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("Live Center"));
    }

        public function render(): View
    {
        return view('pages.club-events.tournaments.⚡live-center.live-center');
    }
};
