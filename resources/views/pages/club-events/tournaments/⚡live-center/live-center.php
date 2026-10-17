<?php

declare(strict_types=1);
use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Services\TournamentFinalPhaseService;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\NewsPostCategoryEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\TournamentStateMachine;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\ReadsTournamentLiveState;
use App\Mail\TournamentResultsMail;
use App\Support\Breadcrumb;
use App\Support\UploadLimits;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, ReadsTournamentLiveState, Toast, WithFileUploads;

    /**
     * L'onglet ouvert au chargement.
     *
     * Résolu dans mount() : le comité arrive sur la régie, qui est son écran de
     * travail, et un joueur -- pour qui l'onglet n'existe pas -- sur les poules.
     */
    public string $activeTab = '';

    /**
     * The score drawer was opened on a match already played: a correction,
     * with its own rules, never a second entry slipping through.
     */
    public bool $correcting = false;

    public bool $createNewsPost = false;

    public bool $launchDrawer = false;

    public string $newsPostContent = '';

    public mixed $newsPostImage = null;

    public string $newsPostTitle = '';

    public int $p1Handicap = 0;

    public int $p2Handicap = 0;

    public string $qrCodeData = '';

    /** @var array{name: string, reference: string, amount_due: float}|array{} */
    public array $qrPaymentDetails = [];

    // ── Score entry drawer

    public bool $scoreDrawer = false;

    public bool $scoresDirty = false;

    public ?int $selectedMatchId = null;

    public ?int $selectedTableId = null;

    // ── Closure tab fields

    public bool $sendThankYou = true;

    /** @var array<int, array{p1: string, p2: string}> */
    public array $setScores = [];

    public bool $showQrModal = false;

    public string $thankYouBody = '';

    public string $thankYouSubject = '';

    public Tournament $tournament;

    /**
     * Open one more table: the hall upstairs when the queue grows.
     */
    public function addTable(int $tableId): void
    {
        abort_unless($this->canManageTournament, 403);

        $table = Table::find($tableId);

        if (! $table instanceof Table || $this->tournament->tables()->where('tables.id', $tableId)->exists()) {
            return;
        }

        $this->tournament->tables()->attach($tableId, ['is_table_free' => true]);
        unset($this->tables, $this->availableTables);
        $this->success(__('Table :name added.', ['name' => $table->name]));
    }

    #[Computed]
    public function allMatchesComplete(): bool
    {
        return ! TournamentMatch::where('tournament_id', $this->tournament->id)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();
    }

    /**
     * Playable tables of the club not used by this tournament yet.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function availableTables(): array
    {
        $used = $this->tournament->tables()->pluck('tables.id');

        return Table::with('room')
            ->whereNotIn('id', $used)
            ->whereNot('state', TableStateEnum::OUT_OF_SERVICE)
            ->orderBy('room_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Table $table): array => ['id' => $table->id, 'name' => ($table->room?->name ?? '—') . ' · ' . __('Table') . ' ' . $table->name])
            ->all();
    }

    #[Computed]
    public function bracketPhaseComplete(): bool
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('round', 'final')
            ->where('status', 'completed')
            ->exists();
    }

    /** @return array<int> */
    #[Computed]
    public function busyPlayerIds(): array
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('status', 'in_progress')
            ->whereExists(fn ($q) => $q
                ->from('table_tournament')
                ->whereColumn('table_tournament.tournament_match_id', 'tournament_matches.id')
                ->where('table_tournament.is_table_free', false)
            )
            ->with(['pair1', 'pair2'])
            ->get()
            ->flatMap(fn (TournamentMatch $m): array => [
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

    /**
     * A match put on a table by mistake goes back to the queue.
     */
    public function cancelLaunch(int $matchId): void
    {
        abort_unless($this->canManageTournament, 403);

        $match = TournamentMatch::where('tournament_id', $this->tournament->id)->find($matchId);

        if (! $match instanceof TournamentMatch || $match->status !== 'in_progress') {
            return;
        }

        DB::transaction(function () use ($match): void {
            $match->sets()->delete();
            $match->update(['status' => 'scheduled']);

            DB::table('table_tournament')
                ->where('tournament_id', $this->tournament->id)
                ->where('tournament_match_id', $match->id)
                ->update(['is_table_free' => true, 'tournament_match_id' => null, 'match_started_at' => null, 'match_ended_at' => null]);
        });

        $this->scoreDrawer = false;
        unset($this->tables, $this->upcomingMatches, $this->queue, $this->busyPlayerIds);
        $this->warning(__('Match back in the queue.'), icon: 'o-arrow-uturn-left');
    }

    // ── Computed: authorization

    #[Computed]
    public function canManageTournament(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(Permission::TournamentsLiveManage->value);
    }

    /**
     * Name another referee for a match, or none: the players count themselves.
     */
    public function changeReferee(int $matchId, ?int $refereeId = null): void
    {
        abort_unless($this->canManageTournament, 403);

        $match = TournamentMatch::where('tournament_id', $this->tournament->id)->with(['pair1', 'pair2'])->find($matchId);

        if (! $match instanceof TournamentMatch || $match->isCompleted()) {
            return;
        }

        if ($refereeId !== null) {
            $isEntrant = $this->tournament->users()
                ->where('users.id', $refereeId)
                ->wherePivotIn('registration_status', ['registered', 'confirmed'])
                ->exists();

            if (! $isEntrant || $match->sidePlayerIds(1)->merge($match->sidePlayerIds(2))->contains($refereeId)) {
                $this->error(__('The referee must be a registered player who is not in the match.'));

                return;
            }
        }

        $match->update(['referee_id' => $refereeId]);
        unset($this->upcomingMatches, $this->queue);
        $this->success($refereeId === null ? __('No referee: the players count themselves.') : __('Referee changed.'));
    }

    public function closeTournament(): void
    {
        abort_unless($this->canManageTournament, 403);

        if (! $this->allMatchesComplete) {
            $this->error(__('All matches must be completed before closing the tournament.'));

            return;
        }

        $this->validate([
            'newsPostImage' => ['nullable', 'image', UploadLimits::documentRule()],
        ]);

        new TournamentStateMachine($this->tournament)->close();

        if ($this->sendThankYou && $this->thankYouSubject !== '' && $this->thankYouBody !== '') {
            $rankings = $this->rankings;

            // Les présents, à leur adresse ou à celle de leur tuteur : un
            // enfant sans adresse n'en reçoit pas moins ses résultats.
            $this->tournament->users()
                ->wherePivotIn('registration_status', ['registered', 'confirmed'])
                ->with('guardians')
                ->get()
                ->filter(fn (User $user): bool => $user->contactEmails() !== [])
                ->each(function (User $user) use ($rankings): void {
                    Mail::to($user->contactEmails())->queue(new TournamentResultsMail(
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
                'title' => $this->newsPostTitle,
                'slug' => Str::slug($this->newsPostTitle . '-' . now()->year),
                'content' => $this->newsPostContent,
                'category' => NewsPostCategoryEnum::COMPETITION,
                'status' => NewsPostStatusEnum::PUBLISHED,
                'image' => $imagePath,
                'user_id' => auth()->id(),
            ]);

            $this->tournament->update(['news_post_id' => $newsPost->id]);
        }

        unset($this->tournamentClosed, $this->allMatchesComplete);
        $this->success(__('Tournament closed. Congratulations to all participants!'));
    }

    public function fillClosureFromRankings(): void
    {
        abort_unless($this->canManageTournament, 403);

        $top3 = $this->rankings->take(3);

        if ($top3->isEmpty()) {
            $this->error(__('No rankings available yet.'));

            return;
        }

        $podiumLines = $top3->map(fn ($e): string => $e['rank'] . '. ' . $this->rankedName($e) . ' (' . $e['result'] . ')')->implode("\n");

        $this->thankYouBody = __('Dear participants,') . "\n\n"
            . __('Thank you for joining us for :name! It was a great day of table tennis.', ['name' => $this->tournament->name]) . "\n\n"
            . __('Final podium:') . "\n" . $podiumLines . "\n\n"
            . __('See you at the next tournament!');

        $this->newsPostContent = '## ' . $this->tournament->name . "\n\n"
            . '**' . __('Podium') . " :**\n\n"
            . $top3->map(fn ($e): string => '- **' . $e['rank'] . '. ' . $this->rankedName($e) . '** — ' . $e['result'])->implode("\n")
            . "\n\n" . __('Congratulations to all participants!');
    }

    /**
     * One side is not at the table: the other wins the match.
     *
     * @param  1|2  $absentSide
     */
    public function forfeit(int $matchId, int $absentSide): void
    {
        abort_unless($this->canManageTournament, 403);

        $match = TournamentMatch::where('tournament_id', $this->tournament->id)->find($matchId);

        if (! $match instanceof TournamentMatch || $match->isCompleted() || $match->player1_id === null || $match->player2_id === null) {
            return;
        }

        app(TournamentMatchService::class)->forfeit($match, $absentSide === 1 ? 1 : 2);
        $this->advanceFromBracket($match->fresh());

        $this->scoreDrawer = false;
        unset($this->tables, $this->upcomingMatches, $this->pools, $this->knockoutMatches, $this->queue, $this->busyPlayerIds);
        $this->warning(__('Walkover recorded.'), icon: 'o-flag');
    }

    // ── Actions: bracket

    public function generateBracket(): void
    {
        abort_unless($this->canManageTournament, 403);

        if (! $this->poolsPhaseComplete) {
            $this->error(__('All pool matches must be completed before creating the bracket.'));

            return;
        }

        // Le tableau existe déjà : le recréer effacerait les matchs joués. Le
        // bouton disparaît, mais une seconde régie ouverte l'affiche encore.
        if ($this->bracketExists) {
            $this->error(__('The bracket already exists.'));

            return;
        }

        $totalQualifiers = $this->tournament->bracket_size
            ?? $this->tournament->nb_pools * $this->tournament->nb_qualifiers_per_pool;
        $startingRound = match (true) {
            $totalQualifiers >= 9 => 'round_16',
            $totalQualifiers >= 5 => 'round_8',
            default => 'round_4',
        };

        try {
            app(TournamentFinalPhaseService::class)
                ->configureKnockoutPhase($this->tournament, $startingRound);
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return;
        }

        unset($this->knockoutMatches, $this->bracketExists, $this->bracketPhaseComplete);
        $this->activeTab = 'bracket';
        $this->success(__('Bracket created!'));
    }

    public function mount(): void
    {
        if ($this->activeTab === '') {
            $this->activeTab = $this->canManageTournament ? 'control-room' : 'pools';
        }

        $this->thankYouSubject = __('Results') . ' — ' . $this->tournament->name;
        $this->thankYouBody = __('Dear participants,') . "\n\n"
            . __('Thank you for joining us for :name! It was a great day of table tennis.', ['name' => $this->tournament->name]) . "\n\n"
            . __('See you at the next tournament!');

        $this->newsPostTitle = __('Results') . ' — ' . $this->tournament->name;
        $this->newsPostContent = '## ' . $this->tournament->name . "\n\n"
            . '**' . __('Podium') . " :**\n\n"
            . "1. \n2. \n3. \n\n"
            . __('Congratulations to all participants!');
    }

    // ── Actions: launch match

    public function openLaunchDrawer(int $tableId): void
    {
        abort_unless($this->canManageTournament, 403);

        $this->selectedTableId = $tableId;
        $this->launchDrawer = true;
    }

    public function openQrPayment(int $userId): void
    {
        abort_unless($this->canManageTournament, 403);

        $registration = TournamentRegistration::where('tournament_id', $this->tournament->id)->where('user_id', $userId)->first();

        if ($registration === null) {
            return;
        }

        $payment = app(TournamentService::class)->ensurePaymentExists($registration, $this->tournament);

        $this->qrPaymentDetails = [
            'name' => $registration->user?->full_name ?? '—',
            'reference' => $payment->reference,
            'amount_due' => (float) $payment->amount_due,
        ];
        $this->qrCodeData = (new GeneratePaymentQR)($payment);
        $this->showQrModal = true;
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

        $this->correcting = $match?->isCompleted() ?? false;

        unset($this->selectedMatch);
        $this->scoresDirty = false;
        $this->scoreDrawer = true;
    }

    /**
     * The pool matches already played, by pool, for a correction.
     *
     * @return Collection<int, Collection<int, TournamentMatch>>
     */
    #[Computed]
    public function playedPoolMatches(): Collection
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->whereNotNull('pool_id')
            ->where('status', 'completed')
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'sets'])
            ->orderBy('match_order')
            ->get()
            ->groupBy('pool_id');
    }

    // ── Computed: tab data

    #[Computed]
    public function poolsPhaseComplete(): bool
    {
        $poolService = app(TournamentPoolService::class);

        return $this->tournament->pools->every(
            fn (Pool $pool) => $poolService->isPoolFinished($pool)
        );
    }

    /**
     * The playable queue, each match already knowing whether it is blocked.
     *
     * The "player already on a table" check lived twice, in Blade: once in the
     * Upcoming tab and once in the launch drawer, with the same intersect
     * against busyPlayerIds written out by hand. Two views of one queue, kept
     * in step manually. They both read this now.
     *
     * The referee counts. busyPlayerIds has always included the referees of the
     * matches in progress — somebody watching a table cannot play on another —
     * but this only ever tested the two sides of the incoming match against it,
     * so a match refereed by somebody already on a table looked perfectly
     * playable and was recommended. Starting it then failed on
     * TournamentMatchService::detectStartConflict(), which does count the
     * incoming referee. The queue said go, the launch said no.
     *
     * @return Collection<int, array{match: TournamentMatch, ready: bool, side1Blocked: bool, side2Blocked: bool, refereeBlocked: bool, blocked: bool}>
     */
    #[Computed]
    public function queue(): Collection
    {
        $busy = $this->busyPlayerIds;

        return $this->upcomingMatches->map(function (TournamentMatch $match) use ($busy): array {
            $ready = $match->player1_id !== null && $match->player2_id !== null;
            $side1Blocked = $ready && $match->sidePlayerIds(1)->intersect($busy)->isNotEmpty();
            $side2Blocked = $ready && $match->sidePlayerIds(2)->intersect($busy)->isNotEmpty();
            $refereeBlocked = $ready && $match->referee_id !== null && in_array($match->referee_id, $busy, true);

            return [
                'match' => $match,
                'ready' => $ready,
                'side1Blocked' => $side1Blocked,
                'side2Blocked' => $side2Blocked,
                'refereeBlocked' => $refereeBlocked,
                // A busy referee is a warning to replace them, not a lock.
                'blocked' => $side1Blocked || $side2Blocked,
            ];
        });
    }

    /**
     * A ranked entrant as people read it: the pair in full, or the player.
     *
     * @param  array<string, mixed>  $entry
     */
    public function rankedName(array $entry): string
    {
        return isset($entry['pair']) && $entry['pair'] !== null
            ? $entry['pair']->fullName()
            : ($entry['user']?->full_name ?? '—');
    }

    public function recordCashPayment(int $userId): void
    {
        abort_unless($this->canManageTournament, 403);

        $register = CashRegister::first();
        $player = User::find($userId);

        if (! $register instanceof CashRegister || ! $player instanceof User) {
            $this->error(__('No cash register found. Create one in Treasury first.'));

            return;
        }

        app(TournamentService::class)->recordCashPayment($this->tournament, $player, $register);
        unset($this->unpaidParticipants);
        $this->success(__('Cash payment recorded.'), icon: 'o-currency-euro');
    }

    /**
     * The registered players who could referee this match right now.
     *
     * @return list<array{id: int, name: string}>
     */
    public function refereeChoices(TournamentMatch $match): array
    {
        $exclude = $match->sidePlayerIds(1)->merge($match->sidePlayerIds(2))->merge($this->busyPlayerIds)->all();

        return $this->tournament->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed'])
            ->whereNotIn('users.id', $exclude)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $player): array => ['id' => $player->id, 'name' => $player->full_name])
            ->all();
    }

    // ── Actions: closure

    public function removeNewsPostImage(): void
    {
        $this->newsPostImage = null;
    }

    /**
     * Close a table for the rest of the day. Never one a match is played on.
     */
    public function removeTable(int $tableId): void
    {
        abort_unless($this->canManageTournament, 403);

        $pivot = $this->tournament->tables()->where('tables.id', $tableId)->first()?->pivot;

        if ($pivot === null) {
            return;
        }

        if (! $pivot->is_table_free) {
            $this->error(__('A match is being played on this table.'));

            return;
        }

        $this->tournament->tables()->detach($tableId);
        unset($this->tables, $this->availableTables);
        $this->success(__('Table removed.'));
    }

    public function render(): View
    {
        return view('pages.club-events.tournaments.⚡live-center.live-center');
    }

    public function rendering(): void
    {
        if ($this->scoreDrawer && $this->selectedMatchId && ! $this->scoresDirty) {
            $this->syncSetScoresFromDb();
        }
    }

    public function saveDraft(): void
    {
        abort_unless($this->canManageTournament, 403);

        $match = TournamentMatch::find($this->selectedMatchId);

        if (! $match) {
            return;
        }

        ['results' => $setResults] = $this->parseSetResults();

        if ($setResults === []) {
            $this->error(__('No set scores to save.'));

            return;
        }

        $match->saveDraft($setResults);
        $this->scoreDrawer = false;
        $this->selectedMatchId = null;
        unset($this->selectedMatch);
        $this->success(__('Sets saved.'));
    }

    #[Computed]
    public function selectedMatch(): ?TournamentMatch
    {
        if (! $this->selectedMatchId) {
            return null;
        }

        return TournamentMatch::with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'sets'])->find($this->selectedMatchId);
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

        DB::transaction(function () use ($matchId): void {
            $rowsUpdated = DB::table('table_tournament')
                ->where('tournament_id', $this->tournament->id)
                ->where('table_id', $this->selectedTableId)
                ->update([
                    'is_table_free' => false,
                    'tournament_match_id' => $matchId,
                    'match_started_at' => now(),
                    'match_ended_at' => null,
                ]);

            if ($rowsUpdated === 0) {
                throw new RuntimeException(__('Table not found or unable to assign match.'));
            }

            TournamentMatch::where('id', $matchId)->update(['status' => 'in_progress']);
        });

        $this->launchDrawer = false;
        $this->selectedTableId = null;
        unset($this->tables, $this->upcomingMatches);

        $this->success(__('Match started!'));
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

        if ($setResults === []) {
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

        if ($this->correcting) {
            if (! $this->correct($match, $setResults)) {
                return;
            }
        } elseif (! app(TournamentMatchService::class)->recordFirstResult($match, $setResults)) {
            $this->scoreDrawer = false;
            $this->error(__('Score already entered: ask the scorers\' table for a correction.'));

            return;
        }

        $this->scoreDrawer = false;
        $this->selectedMatchId = null;
        $this->correcting = false;
        unset($this->tables, $this->upcomingMatches, $this->pools, $this->knockoutMatches, $this->selectedMatch);

        $match->refresh()->loadMissing(['pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2']);
        $isDoubles = $match->pair1_id !== null;
        $winnerName = $isDoubles
            ? ($match->winner_id === $match->player1_id ? $match->pair1?->displayName() : $match->pair2?->displayName())
            : ($match->winner_id === $match->player1_id ? $match->player1?->full_name : $match->player2?->full_name);
        $this->success(($winnerName ?? '—') . ' ' . __('wins!'));
    }

    #[Computed]
    public function tournamentClosed(): bool
    {
        return $this->tournament->status === TournamentStatusEnum::CLOSED;
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
            ->filter(fn ($u): bool => ! isset($paidIds[$u->pivot->payment_id]))
            ->map(fn ($u): array => [
                'user' => $u,
                'qr_confirmed' => (bool) $u->pivot->qr_confirmed,
            ])
            ->values();
    }

    #[Computed]
    public function upcomingMatches(): Collection
    {
        return TournamentMatch::where('tournament_id', $this->tournament->id)
            ->where('status', 'scheduled')
            ->with(['player1', 'player2', 'pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'pool', 'referee'])
            ->orderByRaw('CASE WHEN pool_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN player1_id IS NOT NULL AND player2_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw("CASE round WHEN 'round_16' THEN 1 WHEN 'quarterfinal' THEN 2 WHEN 'semifinal' THEN 3 WHEN 'final' THEN 4 WHEN 'bronze' THEN 5 ELSE 0 END")
            ->orderBy('match_order')
            ->limit(20)
            ->get();
    }

    // ── Lifecycle hooks

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'setScores')) {
            $this->scoresDirty = true;
        }
    }

    // ── Render

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    /**
     * A player leaves the tournament: every match their side still has to play
     * is lost, those already played stand.
     */
    public function withdraw(int $userId): void
    {
        abort_unless($this->canManageTournament, 403);

        $player = User::find($userId);
        $registration = TournamentRegistration::where('tournament_id', $this->tournament->id)
            ->where('user_id', $userId)
            ->whereIn('registration_status', ['registered', 'confirmed'])
            ->first();

        if (! $player instanceof User || $registration === null) {
            return;
        }

        $registration->update(['registration_status' => 'no_show']);

        foreach (app(TournamentMatchService::class)->forfeitRemainingMatches($this->tournament, $player) as $forfeited) {
            $this->advanceFromBracket($forfeited->fresh());
        }

        unset($this->tables, $this->upcomingMatches, $this->pools, $this->knockoutMatches, $this->rankings, $this->queue, $this->busyPlayerIds);
        $this->warning(__(':name withdraws: their remaining matches are lost.', ['name' => $player->full_name]), icon: 'o-flag');
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Live Center'));
    }

    private function advanceFromBracket(?TournamentMatch $match): void
    {
        if ($match instanceof TournamentMatch && $match->round !== null && $match->winner_id !== null) {
            app(TournamentFinalPhaseService::class)->completeMatch($match, $match->winner_id);
            $match->update(['is_forfeit' => true]);
        }
    }

    /**
     * Correct a match already played, within what the tournament allows.
     *
     * @param  array<int, array{player1_score: int, player2_score: int}>  $setResults
     */
    private function correct(TournamentMatch $match, array $setResults): bool
    {
        if ($match->pool_id !== null) {
            if ($this->bracketExists) {
                $this->error(__('The bracket is drawn: a pool score can no longer change who qualified.'));

                return false;
            }

            $match->recordResult($setResults);
            $match->update(['is_forfeit' => false]);

            return true;
        }

        try {
            app(TournamentFinalPhaseService::class)->correctMatch($match, $setResults);
        } catch (LogicException) {
            $this->error(__('The next match has already started: this score can no longer change.'));

            return false;
        }

        return true;
    }

    /**
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
            $isValidSet = $this->tournament->validateSetScore($p1, $p2, count($results), $this->p1Handicap, $this->p2Handicap) === null;
            if ($isValidSet) {
                $p1 > $p2 ? $p1Sets++ : $p2Sets++;
            }

            if ($p1Sets >= $this->tournament->sets_to_win || $p2Sets >= $this->tournament->sets_to_win) {
                break;
            }
        }

        return compact('results', 'p1Sets', 'p2Sets');
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
};
