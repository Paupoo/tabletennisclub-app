<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Data\Tournament\SimulationResult;
use App\Data\Tournament\TournamentConfig;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\TournamentObjectiveEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Livewire\Concerns\HasEventPostForm;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentCancelledNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentInvitationNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentUpdatedNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentWaitlistRemovedNotification;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Competitions\Tournament\Services\TournamentSimulator;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
    {
    use HasBreadcrumbs;
    use HasEventPostForm, Toast, WithFileUploads;

    public bool $bulkDrawer = false;

    public bool $bulkCancelModal = false;

    public bool $inviteIncludeArticle = false;

    public string $inviteMessage = '';

    public int $logistics_buffer = 3;

    public string $matchType = 'single';

    public string $doublesRegistrationMode = 'club';

    // ── Doubles pair composition
    public int $pairPlayer1Id = 0;

    public int $pairPlayer2Id = 0;

    // ── Limite d'inscriptions (0 = illimité)
    public int $maxUsers = 0;

    // ── Étape 2 – Invitations
    public string $memberSearch = '';

    // ── Étape 1 – Config principale
    public string $name = '';

    public int $nb_poules = 4;

    public int $nb_qualifies = 2;

    /** Manual fallback when no rooms are selected. */
    public int $nb_tables = 8;

    public int $pool_size = 4;

    // ── Options statiques
    public array $poolSizeOptions = [
        ['id' => 3, 'name' => '3 joueurs'],
        ['id' => 4, 'name' => '4 joueurs'],
        ['id' => 5, 'name' => '5 joueurs'],
        ['id' => 6, 'name' => '6 joueurs'],
    ];

    // ── Pools staleness flag
    public bool $poolsStale = false;

    // ── Frais d'inscription (0 = gratuit)
    public float $price = 0;

    public bool $publicRegistration = false;

    public array $selectedMembers = [];

    public string $selectedObjective = '';

    // ── Étape 3 – Inscriptions
    public array $selectedPeople = [];

    // ── Contraintes physiques
    public array $selectedRooms = [];

    public array $selectedTags = [];

    public array $setOptions = [
        ['id' => 1, 'name' => '1'],
        ['id' => 2, 'name' => '2'],
        ['id' => 3, 'name' => '3'],
        ['id' => 4, 'name' => '4'],
        ['id' => 5, 'name' => '5'],
    ];

    // ── UI state
    public bool $showCancelModal = false;

    public bool $showRequireCloseRegistrationsModal = false;

    public bool $showCloseRegistrationsModal = false;

    public bool $showInviteModal = false;

    public bool $showOpenRegistrationsModal = false;

    public bool $showLaunchModal = false;

    public bool $showRegisterModal = false;

    // ── Payment modals
    public bool $showQrModal = false;

    public bool $showCashConfirmModal = false;


    public ?int $paymentActionUserId = null;

    public string $qrCodeData = '';

    public array $qrPaymentDetails = [];

    public array $sortBy = ['column' => 'registered_at', 'direction' => 'asc'];

    public string $startTime = '';

    public string $step = '1';

    public array $tagOptions = [
        ['id' => 1, 'name' => 'Tournoi'],
        ['id' => 2, 'name' => 'Interclubs'],
        ['id' => 3, 'name' => 'Jeunes'],
    ];

    // ── Paramètres sportifs
    public int $totalSets = 3;

    public bool $deuceEnabled = true;

    public bool $hasHandicapPoints = true;

    public string $registration_deadline = '';

    public int $memberToRegister = 0;

    public int $tournament_minutes = 180;

    public string $tournamentDate = '';

    // ── Identity (create vs edit)
    public ?int $tournamentId = null;

    // ── Objective suggestion

    public function applyObjectiveSuggestion(): void
    {
        if (empty($this->selectedObjective)) {
            $this->warning(__('Please select an objective first.'));

            return;
        }

        $config = app(TournamentSimulator::class)->suggestOptimalConfig(
            durationMinutes: $this->tournament_minutes,
            nbTables: $this->nbTables,
            objective: TournamentObjectiveEnum::from($this->selectedObjective),
        );

        $this->nb_poules = $config->nbPools;
        $this->pool_size = $config->poolSize;
        $this->nb_qualifies = $config->nbQualifiersPerPool;
        $this->totalSets = $config->setsToWin;
        $this->deuceEnabled = $config->deuceEnabled;
        $this->hasHandicapPoints = $config->hasHandicapPoints;
        $this->logistics_buffer = $config->logisticsBufferMinutes;
        $this->matchType = $config->matchType;

        $this->success(
            title: __('Suggestion applied!'),
            description: TournamentObjectiveEnum::from($this->selectedObjective)->label(),
            icon: 'o-sparkles',
        );
    }

    // ── Computed: rooms from DB

    #[Computed]
    public function availableRooms(): array
    {
        return Room::select(['id', 'name', 'total_playable_tables'])
            ->orderBy('name')
            ->get()
            ->map(fn ($room) => [
                'id' => $room->id,
                'name' => $room->name . ' (' . $room->total_playable_tables . ' tables)',
            ])
            ->toArray();
    }

    public function cancelUserRegistration(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::findOrFail($userId);

        app(TournamentService::class)->cancelRegistration($tournament, $user);

        unset($this->registrations);
        unset($this->waitlist);

        $this->error($user->full_name . ' ' . __('has been unregistered.'));
    }

    public function confirmBulkCancel(): void
    {
        if (empty($this->selectedPeople) || ! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $service = app(TournamentService::class);

        foreach ($this->selectedPeople as $userId) {
            $user = User::find($userId);
            if ($user) {
                $service->cancelRegistration($tournament, $user);
            }
        }

        $this->resetPostAction();
        $this->error(__('Registrations cancelled.'));
    }

    public function confirmBulkNoShow(): void
    {
        if (empty($this->selectedPeople) || ! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->whereIn('user_id', $this->selectedPeople)
            ->update(['registration_status' => 'no_show']);

        $this->resetPostAction();
        $this->warning(__('No-shows recorded.'));
    }

    public function confirmBulkPresence(): void
    {
        if (empty($this->selectedPeople) || ! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->whereIn('user_id', $this->selectedPeople)
            ->update(['registration_status' => 'confirmed']);

        $count = count($this->selectedPeople);
        $this->resetPostAction();
        $this->success("{$count} " . __('presences confirmed.'));
    }

    public function confirmPresence(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update(['registration_status' => 'confirmed']);

        unset($this->registrations);
        $this->success(__('Presence confirmed.'));
    }

    // ── Computed: current tournament model

    #[Computed]
    public function currentTournament(): ?Tournament
    {
        return $this->tournamentId ? Tournament::find($this->tournamentId) : null;
    }

    public function generateMatches(): void
    {
        if (! $this->poolsGenerated) {
            $this->error(__('Generate pools first.'));

            return;
        }

        $tournament = Tournament::with(['pools.users', 'pools.pairs', 'pools.tournament'])->findOrFail($this->tournamentId);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $matchService = app(TournamentMatchService::class);
        foreach ($tournament->pools()->with('users')->get() as $pool) {
            $matchService->assignRefereesToPool($pool);
        }

        $this->success(__('Matches generated!'), icon: 'o-table-cells');
    }

    // ── Pools

    public function generatePools(): void
    {
        if (! $this->tournamentId) {
            $this->error(__('Save the tournament first.'));

            return;
        }

        $tournament = Tournament::with('users')->findOrFail($this->tournamentId);

        if ($tournament->match_type === 'double') {
            if ($tournament->pairs()->count() === 0) {
                $this->error(__('No pairs composed. Please compose pairs before generating pools.'));

                return;
            }
        } elseif ($tournament->users()->count() === 0) {
            $this->error(__('No registered players.'));

            return;
        }

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, $this->nb_poules);

        $this->poolsStale = false;
        $this->success(__('Pools generated!'), icon: 'o-user-group');
    }

    /** True when at least one player is registered or confirmed. */
    #[Computed]
    public function hasRegisteredUsers(): bool
    {
        if (! $this->tournamentId) {
            return false;
        }

        return DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->whereIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->exists();
    }

    // ── Computed: invitation history

    #[Computed]
    public function invitationHistory(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        return DB::table('tournament_invitations')
            ->where('tournament_id', $this->tournamentId)
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'count' => $row->user_count,
                'sent_at' => $row->sent_at,
                'status' => 'Envoyé',
            ])
            ->toArray();
    }

    // ── Computed: field-locking milestones

    /** Name + price are locked once the tournament is validated (status LOCKED or beyond). */
    #[Computed]
    public function isContractLocked(): bool
    {
        if (! $this->tournamentId) {
            return false;
        }

        $status = $this->currentTournament?->status;

        return $status !== null && ! in_array($status, [
            TournamentStatusEnum::DRAFT,
            TournamentStatusEnum::CANCELLED,
        ]);
    }

    public function validateAndLock(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        if (empty($this->name) || empty($this->registration_deadline)) {
            $this->error(__('Tournament name and registration deadline are required before locking.'));

            return;
        }

        Tournament::findOrFail($this->tournamentId)->update(['status' => TournamentStatusEnum::LOCKED]);

        unset($this->currentTournament, $this->isContractLocked);

        $this->step = '4';
        $this->success(__('Tournament validated! Name and price are now locked.'), icon: 'o-lock-closed');
    }

    public function cancelTournament(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::with('users')->findOrFail($this->tournamentId);
        $tournament->update(['status' => TournamentStatusEnum::CANCELLED]);

        $tournament->users()
            ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
            ->get()
            ->each->notify(new TournamentCancelledNotification($tournament));

        unset($this->currentTournament, $this->isContractLocked);
        $this->showCancelModal = false;
        $this->success(__('Tournament cancelled. All registered players have been notified.'), icon: 'o-x-circle');
    }

    public function registerMember(): void
    {
        if (! $this->tournamentId || ! $this->memberToRegister) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::findOrFail($this->memberToRegister);

        try {
            app(TournamentService::class)->registerUser($tournament, $user);
        } catch (\LogicException $e) {
            $this->error($e->getMessage());

            return;
        }

        unset($this->registrations, $this->waitlist, $this->members);

        $this->memberToRegister = 0;
        $this->showRegisterModal = false;
        $this->success($user->full_name . ' ' . __('has been registered.'));
    }

    #[Computed]
    public function registerableMembersOptions(): array
    {
        $alreadyRegistered = $this->tournamentId
            ? DB::table('tournament_user')
                ->where('tournament_id', $this->tournamentId)
                ->whereIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
                ->pluck('user_id')
                ->toArray()
            : [];

        return User::where('is_active', true)
            ->whereNotIn('id', $alreadyRegistered)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name . ' (' . ($u->ranking ?? 'NC') . ')',
            ])
            ->toArray();
    }

    // ── Launch

    public function launch(): void
    {
        if (! $this->registrationClosed) {
            $this->showRequireCloseRegistrationsModal = true;

            return;
        }

        if (! $this->matchesGenerated) {
            $this->error(__('Generate matches first.'));

            return;
        }

        if ($this->poolsStale) {
            $this->error(__('Configuration changed — regenerate pools and matches first.'));

            return;
        }

        $this->showLaunchModal = true;
        $this->js('$wire.processLaunch()');
    }

    public function confirmCloseAndLaunch(): void
    {
        $this->confirmCloseRegistrations();
        $this->showRequireCloseRegistrationsModal = false;
        $this->launch();
    }

    public function markNoShow(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update(['registration_status' => 'no_show']);

        $forfeited = 0;
        if ($this->isLaunched) {
            $tournament = Tournament::findOrFail($this->tournamentId);
            $user = User::findOrFail($userId);
            $forfeited = app(\App\Services\TournamentMatchService::class)
                ->forfeitPoolMatchesForPlayer($tournament, $user);
        }

        unset($this->registrations);

        $msg = $forfeited > 0
            ? __('No-show recorded. :count pool match(es) forfeited.', ['count' => $forfeited])
            : __('No-show recorded.');

        $this->warning($msg, icon: 'o-no-symbol');
    }

    // ── Payment actions

    public function openQrModal(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::findOrFail($userId);
        $registration = TournamentRegistration::where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $payment = app(TournamentService::class)->ensurePaymentExists($registration, $tournament);

        $this->qrPaymentDetails = [
            'name'        => $user->full_name,
            'reference'   => $payment->reference,
            'amount_due'  => $payment->amount_due,
            'iban'        => Club::ourClub()->first()->bank_account,
            'bic'         => Club::ourClub()->first()->bic,
            'beneficiary' => 'CTT Ottignies-Blocry ASBL',
        ];
        $this->qrCodeData = (new GeneratePaymentQR)($payment);
        $this->paymentActionUserId = $userId;
        $this->showQrModal = true;
    }

    public function openCashConfirmModal(int $userId): void
    {
        $register = CashRegister::first();
        if (! $register) {
            $this->error(__('No cash register found. Create one in Treasury first.'));
            return;
        }

        $this->paymentActionUserId = $userId;
        $this->showCashConfirmModal = true;
    }

    public function confirmCashPayment(): void
    {
        if (! $this->tournamentId || ! $this->paymentActionUserId) {
            return;
        }

        $register = CashRegister::first();
        if (! $register) {
            $this->error(__('No cash register found.'));
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::findOrFail($this->paymentActionUserId);

        app(TournamentService::class)->recordCashPayment($tournament, $user, $register);

        unset($this->registrations);
        $this->reset(['showCashConfirmModal', 'paymentActionUserId']);
        $this->success(__('Cash payment recorded.'), icon: 'o-currency-euro');
    }

    public function markQrConfirmed(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update(['qr_confirmed' => true]);

        unset($this->registrations);
        $this->reset(['showQrModal', 'paymentActionUserId']);
        $this->success(__('QR payment confirmed on-site.'), icon: 'o-check-circle');
    }

    // ── Computed: matches list for verification

    #[Computed]
    public function matchesByPool(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        $tournament = Tournament::find($this->tournamentId);
        $isDoubles = $tournament->match_type === 'double';

        return $tournament
            ->matches()
            ->with($isDoubles
                ? ['pair1.player1', 'pair1.player2', 'pair2.player1', 'pair2.player2', 'pool']
                : ['player1', 'player2', 'pool']
            )
            ->whereNotNull('pool_id')
            ->orderBy('pool_id')
            ->orderBy('match_order')
            ->get()
            ->groupBy('pool_id')
            ->map(fn ($matches, $poolId) => [
                'name' => $matches->first()->pool?->name ?? "Pool {$poolId}",
                'matches' => $matches->map(fn ($m) => [
                    'order' => $m->match_order,
                    'p1' => $isDoubles ? ($m->pair1?->displayName() ?? '—') : ($m->player1?->full_name ?? '—'),
                    'p2' => $isDoubles ? ($m->pair2?->displayName() ?? '—') : ($m->player2?->full_name ?? '—'),
                ])->toArray(),
            ])
            ->toArray();
    }

    #[Computed]
    public function matchesGenerated(): bool
    {
        return $this->tournamentId !== null
            && (bool) Tournament::find($this->tournamentId)?->matches()->exists();
    }

    // ── Computed: members from DB

    #[Computed]
    public function members(): array
    {
        $query = User::active()->orderBy('last_name');

        if ($this->selectedObjective === TournamentObjectiveEnum::Competitive->value) {
            $query->competitor();
        }

        if ($this->tournamentId) {
            $alreadyInvolved = DB::table('tournament_user')
                ->where('tournament_id', $this->tournamentId)
                ->whereIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
                ->pluck('user_id');

            $query->whereNotIn('id', $alreadyInvolved);
        }

        return $query->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'email' => $u->email,
                'ranking' => $u->ranking ?? 'NC',
            ])
            ->toArray();
    }

    // ── Lifecycle

    public function mount(?Tournament $tournament = null): void
    {
        $this->tournamentDate = today()->addWeek()->format('Y-m-d');

        if ($tournament !== null) {
            $this->tournamentId = $tournament->id;
            $this->name = $tournament->name;
            $this->tournamentDate = $tournament->start_date?->format('Y-m-d') ?? $this->tournamentDate;
            $this->startTime = $tournament->start_time ?? '';
            $this->registration_deadline = $tournament->registration_deadline?->format('Y-m-d') ?? '';
            $this->publicRegistration = false;
            $this->tournament_minutes = $tournament->duration_minutes;
            $this->logistics_buffer = $tournament->logistics_buffer_minutes;
            $this->totalSets = $tournament->sets_to_win;
            $this->deuceEnabled = $tournament->deuce_enabled;
            $this->hasHandicapPoints = $tournament->has_handicap_points;
            $this->matchType = $tournament->match_type;
            $this->doublesRegistrationMode = $tournament->doubles_registration_mode ?? 'club';
            $this->nb_poules = $tournament->nb_pools;
            $this->pool_size = $tournament->pool_size;
            $this->nb_qualifies = $tournament->nb_qualifiers_per_pool;
            $this->maxUsers = $tournament->max_users;
            $this->price = (float) ($tournament->price ?? 0);
            $this->selectedObjective = $tournament->objective?->value ?? '';
            $this->selectedRooms = $tournament->rooms->pluck('id')->toArray();
            $this->nb_tables = (int) $tournament->rooms->sum('total_playable_tables') ?: 8;

            $this->step = match ($tournament->status) {
                TournamentStatusEnum::LOCKED    => '4',
                TournamentStatusEnum::PUBLISHED => '4',
                TournamentStatusEnum::SETUP     => '5',
                TournamentStatusEnum::PENDING,
                TournamentStatusEnum::CLOSED    => '6',
                default                         => '1',
            };

            $this->initEventPost($tournament->eventPost, $tournament->name);
        }

        // Pre-fill location from the first room's address if not already set
        if ($this->eventLocation === '') {
            $roomId = $this->selectedRooms[0] ?? null;
            $room   = $roomId
                ? \App\Domains\ClubAdmin\Club\Models\Room::find($roomId)
                : \App\Domains\ClubAdmin\Club\Models\Room::first();

            if ($room) {
                $this->eventLocation = implode(', ', array_filter([
                    $room->street,
                    $room->city_name,
                ]));
            }
        }
    }

    // ── Computed: effective table count

    #[Computed]
    public function nbTables(): int
    {
        if (empty($this->selectedRooms)) {
            return $this->nb_tables;
        }

        $total = Room::whereIn('id', $this->selectedRooms)->sum('total_playable_tables');

        return (int) ($total ?: $this->nb_tables);
    }

    // ── Computed: pools from DB

    #[Computed]
    public function pools(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        $tournament = Tournament::find($this->tournamentId);

        if ($tournament->match_type === 'double') {
            return $tournament
                ->pools()
                ->with(['pairs.player1', 'pairs.player2'])
                ->get()
                ->mapWithKeys(fn (Pool $pool) => [
                    $pool->id => [
                        'name' => $pool->name,
                        'players' => $pool->pairs->map(fn ($pair) => [
                            'id' => $pair->id,
                            'name' => $pair->displayName(),
                            'rank' => $pair->averageRanking(),
                            'pts' => 0,
                        ])->toArray(),
                    ],
                ])
                ->toArray();
        }

        return $tournament
            ->pools()
            ->with(['users' => fn ($q) => $q
                ->orderByRaw('ranking IS NULL')
                ->orderBy('ranking')
                ->orderBy('last_name')
                ->orderBy('first_name'),
            ])
            ->get()
            ->mapWithKeys(fn (Pool $pool) => [
                $pool->id => [
                    'name' => $pool->name,
                    'players' => $pool->users->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->full_name,
                        'rank' => $u->ranking ?? 'NC',
                        'pts' => 0,
                    ])->toArray(),
                ],
            ])
            ->toArray();
    }

    // ── Computed: pool/match generation guards

    #[Computed]
    public function poolsGenerated(): bool
    {
        return $this->tournamentId !== null
            && (bool) Tournament::find($this->tournamentId)?->pools()->exists();
    }

    public function processLaunch(): mixed
    {
        if (! $this->tournamentId) {
            return null;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        if (! $tournament->pools()->exists()) {
            $this->error(__('Generate pools first.'));

            return null;
        }

        if (! $tournament->matches()->exists()) {
            $this->error(__('Generate matches first.'));

            return null;
        }

        $tournament->update(['status' => TournamentStatusEnum::PENDING]);

        // Populate table_tournament pivot from the tournament's linked rooms
        $tableIds = Table::whereHas('room', fn ($q) => $q->whereIn('rooms.id', $tournament->rooms()->pluck('rooms.id')))
            ->pluck('id');

        $tournament->tables()->sync(
            $tableIds->mapWithKeys(fn ($id) => [$id => ['is_table_free' => true]])->all()
        );

        return redirect()->route('admin.tournaments.live-center', $tournament->id);
    }

    public function promoteFromWaitlist(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        if ($tournament->activeRegistrationsCount() >= $tournament->max_users && $tournament->max_users > 0) {
            $this->error(__('No available spot. Cancel a registration first.'));

            return;
        }

        $user = User::findOrFail($userId);

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update([
                'registration_status' => 'registered',
                'waitlist_position' => null,
            ]);

        app(TournamentService::class)->countRegisteredUsers($tournament);
        unset($this->registrations);
        unset($this->waitlist);

        $this->success($user->full_name . ' ' . __('has been moved to the registered list.'));
    }

    protected function resolveEventPostData(): array
    {
        $tournament = Tournament::findOrFail($this->tournamentId);

        $startTime = $tournament->start_time
            ? Carbon::parse($tournament->start_time)
            : null;

        $endTime = $startTime && $tournament->duration_minutes > 0
            ? $startTime->copy()->addMinutes($tournament->duration_minutes)
            : null;

        return [
            'model'            => $tournament,
            'type'             => ClubEventTypeEnum::TOURNAMENT,
            'icon'             => '🏆',
            'event_date'       => $tournament->start_date->toDateString(),
            'start_time'       => $startTime?->format('H:i:s') ?? '00:00:00',
            'end_time'         => $endTime?->format('H:i:s'),
            'price'            => (string) $tournament->price,
            'max_participants' => $tournament->max_users ?: null,
        ];
    }

    // ── Computed: registration status

    #[Computed]
    public function registrationClosed(): bool
    {
        return $this->currentTournament !== null && $this->currentTournament->status === TournamentStatusEnum::SETUP;
    }

    #[Computed]
    public function isLaunched(): bool
    {
        $status = $this->currentTournament?->status;

        return $status !== null && in_array($status, [TournamentStatusEnum::PENDING, TournamentStatusEnum::CLOSED]);
    }

    // ── Computed: active registrations (not waiting, not cancelled)

    #[Computed]
    public function registrations(): Collection
    {
        if (! $this->tournamentId) {
            return collect();
        }

        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        $users = Tournament::findOrFail($this->tournamentId)
            ->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'no_show'])
            ->get();

        $paymentIds = $users->map(fn (User $u) => $u->pivot->payment_id)->filter()->unique()->values();
        $paidPaymentIds = Payment::whereIn('id', $paymentIds)->where('status', 'paid')->pluck('id')->flip();

        $rows = $users->map(fn (User $u) => [
            'id'               => $u->id,
            'name'             => $u->full_name,
            'ranking'          => $u->ranking ?? 'NC',
            'status'           => $u->pivot->registration_status,
            'has_paid'         => (bool) $u->pivot->has_paid || isset($paidPaymentIds[$u->pivot->payment_id]),
            'qr_confirmed'     => (bool) $u->pivot->qr_confirmed,
            'payment_id'       => $u->pivot->payment_id,
            'payment_deadline' => $u->pivot->payment_deadline,
            'registered_at'    => $u->pivot->created_at,
        ]);

        return $dir === 'asc'
            ? $rows->sortBy($col)->values()
            : $rows->sortByDesc($col)->values();
    }

    // ── Pair management (doubles)

    public function createPair(): void
    {
        if (! $this->tournamentId || ! $this->pairPlayer1Id || ! $this->pairPlayer2Id) {
            $this->error(__('Select two different players.'));

            return;
        }

        if ($this->pairPlayer1Id === $this->pairPlayer2Id) {
            $this->error(__('A player cannot be paired with themselves.'));

            return;
        }

        $existing = TournamentPair::where('tournament_id', $this->tournamentId)
            ->where(fn ($q) => $q
                ->whereIn('player1_id', [$this->pairPlayer1Id, $this->pairPlayer2Id])
                ->orWhereIn('player2_id', [$this->pairPlayer1Id, $this->pairPlayer2Id])
            )
            ->exists();

        if ($existing) {
            $this->error(__('One of these players is already in a pair.'));

            return;
        }

        TournamentPair::create([
            'tournament_id' => $this->tournamentId,
            'player1_id' => $this->pairPlayer1Id,
            'player2_id' => $this->pairPlayer2Id,
            'registered_by' => Auth::id() ?? 0,
        ]);

        unset($this->pairs);
        $this->pairPlayer1Id = 0;
        $this->pairPlayer2Id = 0;
        $this->success(__('Pair created.'), icon: 'o-user-group');
    }

    public function deletePair(int $pairId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        TournamentPair::where('id', $pairId)
            ->where('tournament_id', $this->tournamentId)
            ->delete();

        unset($this->pairs);
        $this->warning(__('Pair deleted.'));
    }

    #[Computed]
    public function pairs(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        return TournamentPair::where('tournament_id', $this->tournamentId)
            ->with(['player1', 'player2'])
            ->get()
            ->map(fn (TournamentPair $p) => [
                'id'      => $p->id,
                'name'    => $p->displayName(),
                'p1_id'   => $p->player1_id,
                'p1_name' => $p->player1?->full_name ?? '?',
                'p2_id'   => $p->player2_id,
                'p2_name' => $p->player2?->full_name ?? '?',
            ])
            ->toArray();
    }

    #[Computed]
    public function unpaired(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        $pairedIds = TournamentPair::where('tournament_id', $this->tournamentId)
            ->get()
            ->flatMap(fn ($p) => [$p->player1_id, $p->player2_id])
            ->unique()
            ->toArray();

        return Tournament::findOrFail($this->tournamentId)
            ->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->whereNotIn('users.id', $pairedIds)
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    public function removeFromWaitlist(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $user = User::find($userId);

        DB::table('tournament_user')
            ->where('tournament_id', $this->tournamentId)
            ->where('user_id', $userId)
            ->update(['registration_status' => 'cancelled']);

        if ($user) {
            $user->notify(new TournamentWaitlistRemovedNotification($tournament));
        }

        unset($this->registrations);
        unset($this->waitlist);

        $this->success(__('Removed from waiting list.'));
    }

    // ── Render

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Tournaments Wizard'));
    }

    public function render(): mixed
    {
        $search = strtolower($this->memberSearch);
        $filteredMembers = empty($search)
            ? $this->members
            : array_values(array_filter(
                $this->members,
                fn ($m) => str_contains(strtolower($m['name']), $search)
                    || str_contains(strtolower($m['email'] ?? ''), $search)
            ));

        return $this->view([
            'filteredMembers' => $filteredMembers,
        ]);
    }

    // ── Save (create or update)

    public function save(): void
    {
        $this->validate([
            'name' => 'required|min:3|max:255',
            'tournamentDate' => 'required|date',
            'tournament_minutes' => 'required|integer|min:30|max:1440',
            'nb_poules' => 'required|integer|min:1|max:64',
            'pool_size' => 'required|integer|min:2|max:10',
            'nb_qualifies' => 'required|integer|min:1',
            'totalSets' => 'required|integer|min:1|max:5',
            'deuceEnabled' => 'boolean',
            'hasHandicapPoints' => 'boolean',
            'logistics_buffer' => 'required|integer|min:0|max:30',
            'matchType' => 'required|in:single,double',
            'doublesRegistrationMode' => 'nullable|in:club,self',
            'maxUsers' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        // Snapshot logistical values before saving so we can detect changes.
        $logisticsChanged = [];
        if ($this->tournamentId) {
            $existing = Tournament::with('rooms')->find($this->tournamentId);
            if ($existing) {
                $oldDate = $existing->start_date?->format('Y-m-d');
                $oldTime = $existing->start_time;
                $oldRooms = $existing->rooms->pluck('id')->sort()->values()->toArray();

                if ($oldDate !== $this->tournamentDate) {
                    $logisticsChanged[] = 'date';
                }
                if ($oldTime !== ($this->startTime ?: null)) {
                    $logisticsChanged[] = 'time';
                }
                $newRooms = collect($this->selectedRooms)->sort()->values()->toArray();
                if ($oldRooms !== $newRooms) {
                    $logisticsChanged[] = 'rooms';
                }
            }
        }

        $tournament = Tournament::updateOrCreate(
            ['id' => $this->tournamentId],
            [
                'name' => $this->name,
                'start_date' => $this->tournamentDate,
                'start_time' => $this->startTime ?: null,
                'duration_minutes' => $this->tournament_minutes,
                'pool_size' => $this->pool_size,
                'nb_pools' => $this->nb_poules,
                'nb_qualifiers_per_pool' => $this->nb_qualifies,
                'sets_to_win' => $this->totalSets,
                'deuce_enabled' => $this->deuceEnabled,
                'has_handicap_points' => $this->hasHandicapPoints,
                'logistics_buffer_minutes' => $this->logistics_buffer,
                'match_type' => $this->matchType,
                'doubles_registration_mode' => $this->matchType === 'double' ? $this->doublesRegistrationMode : null,
                'objective' => $this->selectedObjective ?: null,
                'max_users' => $this->maxUsers,
                'price' => $this->price,
                'registration_deadline' => $this->registration_deadline ?: null,
            ]
        );

        $tournament->rooms()->sync($this->selectedRooms);

        $this->tournamentId = $tournament->id;

        // Notify registered players when logistical details changed.
        if (! empty($logisticsChanged) && $this->hasRegisteredUsers) {
            unset($this->hasRegisteredUsers);
            $tournament->users()
                ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered'])
                ->get()
                ->each->notify(new TournamentUpdatedNotification($tournament, $logisticsChanged));
        }

        unset($this->isContractLocked, $this->hasRegisteredUsers, $this->currentTournament);

        if ($this->step === '1') {
            $this->step = '2';
        }

        $this->success(
            title: __('Tournament saved!'),
            description: __('Configuration updated.'),
            icon: 'o-check-circle',
        );
    }

    // ── Invitations

    public function selectAllMembers(): void
    {
        $this->selectedMembers = array_column($this->members, 'id');
    }

    public function selectNoMembers(): void
    {
        $this->selectedMembers = [];
    }

    public function sendInvitations(): void
    {
        if (empty($this->selectedMembers) || ! $this->tournamentId) {
            return;
        }

        if (empty($this->registration_deadline)) {
            $this->error(__('A registration deadline is required before sending invitations.'));

            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        if (! in_array($tournament->status, [TournamentStatusEnum::LOCKED, TournamentStatusEnum::PUBLISHED])) {
            $this->error(__('Invitations cannot be sent while registrations are closed.'));

            return;
        }
        $users = User::whereIn('id', $this->selectedMembers)->get();

        $notification = new TournamentInvitationNotification(
            tournament: $tournament,
            customMessage: $this->inviteMessage,
            includeArticleLink: $this->inviteIncludeArticle && $this->eventPostId !== null,
            newsPostId: $this->inviteIncludeArticle ? $this->eventPostId : null,
        );

        foreach ($users as $user) {
            $user->notify($notification);
        }

        DB::table('tournament_invitations')->insert([
            'tournament_id' => $this->tournamentId,
            'user_count' => $users->count(),
            'message' => $this->inviteMessage ?: null,
            'include_article' => $this->inviteIncludeArticle && $this->eventPostId !== null,
            'sent_at' => now(),
        ]);

        $count = $users->count();
        $this->success(
            title: __('Invitations sent!'),
            description: "{$count} " . __('members have been notified.'),
            icon: 'o-paper-airplane',
        );

        // First invitation transitions locked → published and advances to registrations.
        if ($tournament->status === TournamentStatusEnum::LOCKED) {
            $tournament->update(['status' => TournamentStatusEnum::PUBLISHED]);
            unset($this->currentTournament, $this->isContractLocked);
            $this->step = '5';
        }

        $this->showInviteModal = false;
        $this->inviteMessage = '';
        $this->selectedMembers = [];
        unset($this->invitationHistory);
    }

    // ── Computed: table efficiency (referee constraint)

    /**
     * Idle tables given the referee rule (3 people per singles match, 5 per doubles).
     *
     * @return array{idle: int, usefulTables: int, extraPools: int, suggestedNbPools: int, nextBetterPoolSize: int|null}
     */
    #[Computed]
    public function tableEfficiency(): array
    {
        $tablesPerPool = $this->matchType === 'double'
            ? max(1, (int) floor($this->pool_size * 2 / 5))
            : max(1, (int) floor($this->pool_size / 3));

        $usefulTables = $this->nb_poules * $tablesPerPool;
        $idle = max(0, $this->nbTables - $usefulTables);
        $extraPools = $tablesPerPool > 0 ? (int) floor($idle / $tablesPerPool) : 0;

        $nextBetterPoolSize = null;

        foreach (range($this->pool_size + 1, 6) as $size) {
            $candidate = $this->matchType === 'double'
                ? max(1, (int) floor($size * 2 / 5))
                : max(1, (int) floor($size / 3));

            if ($candidate > $tablesPerPool) {
                $nextBetterPoolSize = $size;
                break;
            }
        }

        return [
            'idle' => $idle,
            'usefulTables' => $usefulTables,
            'extraPools' => $extraPools,
            'suggestedNbPools' => $this->nb_poules + $extraPools,
            'nextBetterPoolSize' => $nextBetterPoolSize,
        ];
    }

    // ── Computed: simulation

    #[Computed]
    public function simulation(): SimulationResult
    {
        return app(TournamentSimulator::class)->simulate(new TournamentConfig(
            durationMinutes: max(1, $this->tournament_minutes),
            nbTables: max(1, $this->nbTables),
            logisticsBufferMinutes: max(0, $this->logistics_buffer),
            poolSize: max(2, $this->pool_size),
            nbPools: max(1, $this->nb_poules),
            nbQualifiersPerPool: max(1, $this->nb_qualifies),
            setsToWin: max(1, $this->totalSets),
            matchType: $this->matchType,
        ));
    }

    public function toggleMember(int $id): void
    {
        if (in_array($id, $this->selectedMembers)) {
            $this->selectedMembers = array_values(
                array_filter($this->selectedMembers, fn ($m) => $m !== $id)
            );
        } else {
            $this->selectedMembers[] = $id;
        }
    }

    // ── Registrations

    public function openToggleRegistrationsModal(): void
    {
        if ($this->registrationClosed) {
            $this->showOpenRegistrationsModal = true;
        } else {
            $this->showCloseRegistrationsModal = true;
        }
    }

    public function confirmCloseRegistrations(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        // Kick everyone still on the waitlist — they no longer have a chance.
        $tournament->users()
            ->wherePivotIn('registration_status', ['waiting'])
            ->get()
            ->each(function (User $user) use ($tournament): void {
                DB::table('tournament_user')
                    ->where('tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->update(['registration_status' => 'cancelled', 'waitlist_position' => null]);

                $user->notify(new TournamentWaitlistRemovedNotification($tournament));
            });

        $tournament->update(['status' => TournamentStatusEnum::SETUP]);

        unset($this->currentTournament, $this->waitlist, $this->registrations);
        $this->showCloseRegistrationsModal = false;
        $this->success(__('Registrations closed. Waitlisted players have been notified.'), icon: 'o-lock-closed');
    }

    public function confirmOpenRegistrations(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $tournament->update(['status' => TournamentStatusEnum::PUBLISHED]);

        unset($this->currentTournament);
        $this->showOpenRegistrationsModal = false;
        $this->success(__('Registrations are now open.'), icon: 'o-lock-open');
    }

    public function updatedMatchType(): void
    {
        $this->markPoolsStaleIfGenerated();
    }

    // ── Hooks

    public function updatedNbPoules(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->suggestMaxUsers();
    }

    public function updatedNbQualifies(): void
    {
        $this->markPoolsStaleIfGenerated();
    }

    public function updatedPoolSize(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->suggestMaxUsers();
    }

    public function updatedSelectedRooms(): void
    {
        if (! empty($this->selectedRooms)) {
            $total = Room::whereIn('id', $this->selectedRooms)->sum('total_playable_tables');

            if ($total > 0) {
                $this->nb_tables = (int) $total;
            }
        }
    }

    public function updatedTotalSets(): void
    {
        $this->markPoolsStaleIfGenerated();
    }

    public function updateStructure(array $newStructure): void
    {
        foreach ($newStructure as $entry) {
            $pool = Pool::find($entry['teamId']);

            if ($pool && $pool->tournament_id === $this->tournamentId) {
                $pool->users()->sync($entry['memberIds']);
            }
        }
    }

    public function viewBatchDetails(int $_batchId): void {}

    // ── Computed: waiting list ordered by position

    #[Computed]
    public function waitlist(): Collection
    {
        if (! $this->tournamentId) {
            return collect();
        }

        return Tournament::findOrFail($this->tournamentId)
            ->users()
            ->wherePivot('registration_status', 'waiting')
            ->orderByPivot('waitlist_position')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'ranking' => $u->ranking ?? 'NC',
                'position' => $u->pivot->waitlist_position,
                'registered_at' => $u->pivot->created_at,
            ]);
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'objectiveOptions' => TournamentObjectiveEnum::toOptions(),
            'maxUsers' => $this->maxUsers,
        ];
    }

    private function markPoolsStaleIfGenerated(): void
    {
        if ($this->poolsGenerated || $this->matchesGenerated) {
            $this->poolsStale = true;
        }
    }

    private function resetPostAction(): void
    {
        $this->selectedPeople = [];
        $this->bulkDrawer = false;
        unset($this->registrations);
        unset($this->waitlist);
    }

    // ── Private helpers

    private function suggestMaxUsers(): void
    {
        $capacity = $this->nb_poules * $this->pool_size;
        // Only auto-update if unset (0) or if it matches the previous auto-computed value.
        if ($this->maxUsers === 0 || $this->maxUsers === $capacity) {
            $this->maxUsers = $capacity;
        }
    }
};
