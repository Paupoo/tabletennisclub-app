<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Data\Tournament\SimulationResult;
use App\Data\Tournament\TournamentConfig;
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
use App\Domains\Competitions\Tournament\Notifications\TournamentUpdatedNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentWaitlistRemovedNotification;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Competitions\Tournament\Services\TournamentSimulator;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\TournamentObjectiveEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\TournamentStateMachine;
use App\Jobs\SendTournamentInvitationJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasEventPostForm;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs;
    use HasEventPostForm, Toast, WithFileUploads;

    public bool $bulkCancelModal = false;

    public bool $bulkDrawer = false;

    public bool $deuceEnabled = true;

    public string $description = '';

    public string $doublesRegistrationMode = 'club';

    public bool $hasHandicapPoints = true;

    public bool $inviteIncludeArticle = false;

    public string $inviteMessage = '';

    public int $logistics_buffer = 3;

    public string $matchType = 'single';

    // ── Limite d'inscriptions (0 = illimité)
    public int $maxUsers = 0;

    /**
     * Le plafond a-t-il été saisi à la main ?
     *
     * Tant qu'il ne l'est pas, il suit la structure (poules × joueurs par poule).
     * Une fois saisi, plus rien ne l'écrase — c'est la salle qui s'adapte au
     * tournoi voulu, pas l'inverse (issue #37).
     */
    public bool $maxUsersManual = false;

    // ── Étape 2 – Invitations
    public string $memberSearch = '';

    public int $memberToRegister = 0;

    // ── Étape 1 – Config principale
    public string $name = '';

    public int $nb_poules = 4;

    public int $nb_qualifies = 2;

    /** Manual fallback when no rooms are selected. */
    public int $nb_tables = 8;

    // ── Doubles pair composition
    public int $pairPlayer1Id = 0;

    public int $pairPlayer2Id = 0;

    public ?int $paymentActionUserId = null;

    public int $pool_size = 4;

    // ── Options statiques
    /** @var array<int, array{id: int, name: string}> */
    public array $poolSizeOptions = [];

    // ── Pools staleness flag
    public bool $poolsStale = false;

    // ── Frais d'inscription (0 = gratuit)
    public float $price = 0;

    public bool $publicRegistration = false;

    public string $qrCodeData = '';

    public array $qrPaymentDetails = [];

    public string $registration_deadline = '';

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

    public bool $showCashConfirmModal = false;

    public bool $showCloseRegistrationsModal = false;

    public bool $showInviteModal = false;

    public bool $showLaunchModal = false;

    public bool $showOpenRegistrationsModal = false;

    // ── Payment modals
    public bool $showQrModal = false;

    public bool $showRegisterModal = false;

    public bool $showRequireCloseRegistrationsModal = false;

    public array $sortBy = ['column' => 'registered_at', 'direction' => 'asc'];

    public string $startTime = '';

    /**
     * L'étape courante de l'assistant.
     *
     * Dans l'URL pour que le lien soit partageable, que le retour navigateur
     * fonctionne, et qu'un rechargement ne renvoie pas ailleurs.
     *
     * La valeur par défaut est vide, et non '1' : c'est le seul moyen de
     * distinguer « aucune étape demandée » — où le statut décide — de
     * « ouvre-moi l'étape 1 », que l'icône réglages de la liste demande
     * explicitement. mount() résout le vide avant le premier rendu.
     */
    #[Url(except: '')]
    public string $step = '';

    public array $tagOptions = [
        ['id' => 1, 'name' => 'Tournoi'],
        ['id' => 2, 'name' => 'Interclubs'],
        ['id' => 3, 'name' => 'Jeunes'],
    ];

    // ── Paramètres sportifs
    public int $totalSets = 3;

    public int $tournament_minutes = 180;

    public string $tournamentDate = '';

    // ── Identity (create vs edit)
    public ?int $tournamentId = null;

    // ── Objective suggestion

    public function applyObjectiveSuggestion(): void
    {
        if ($this->selectedObjective === '' || $this->selectedObjective === '0') {
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

        // La suggestion redessine les poules : sans ça le plafond restait celui
        // de la configuration précédente, sans que rien ne le signale.
        $this->suggestMaxUsers();

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
            ->map(fn ($room): array => [
                'id' => $room->id,
                'name' => $room->name . ' (' . $room->total_playable_tables . ' tables)',
            ])
            ->toArray();
    }

    public function cancelTournament(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::with('users')->findOrFail($this->tournamentId);

        try {
            (new TournamentStateMachine($tournament))->cancel();
        } catch (LogicException) {
            $this->showCancelModal = false;
            $this->error(__('Matches have already been played: this tournament can no longer be cancelled.'));

            return;
        } catch (InvalidArgumentException) {
            $this->showCancelModal = false;
            $this->error(__('This tournament is already over, so there is nothing left to cancel.'));

            return;
        }

        $tournament->users()
            ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
            ->get()
            ->each->notify(new TournamentCancelledNotification($tournament));

        unset($this->currentTournament, $this->isContractLocked);
        $this->showCancelModal = false;
        $this->success(__('Tournament cancelled. All registered players have been notified.'), icon: 'o-x-circle');
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

    /**
     * Reste-t-il un geste d'ouverture à poser ?
     *
     * Verrouillé : le tournoi n'a jamais été ouvert. Configuration : il l'a été,
     * puis refermé. Les deux se rouvrent par le même bouton.
     */
    #[Computed]
    public function canOpenRegistrations(): bool
    {
        return $this->currentTournament?->state()
            ->canTransitionTo(TournamentStatusEnum::PUBLISHED) ?? false;
    }

    public function confirmBulkCancel(): void
    {
        if ($this->selectedPeople === [] || ! $this->tournamentId) {
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
        if ($this->selectedPeople === [] || ! $this->tournamentId) {
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
        if ($this->selectedPeople === [] || ! $this->tournamentId) {
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

    public function confirmCloseAndLaunch(): void
    {
        $this->confirmCloseRegistrations();
        $this->showRequireCloseRegistrationsModal = false;
        $this->launch();
    }

    public function confirmCloseRegistrations(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        try {
            (new TournamentStateMachine($tournament))->setUp();
        } catch (InvalidArgumentException) {
            $this->showCloseRegistrationsModal = false;
            $this->error(__('Nobody has registered yet, so there are no registrations to close. Cancel the tournament instead.'));

            return;
        }

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

        unset($this->currentTournament, $this->waitlist, $this->registrations, $this->registrationsOpen, $this->canOpenRegistrations);
        $this->showCloseRegistrationsModal = false;
        $this->success(__('Registrations closed. Waitlisted players have been notified.'), icon: 'o-lock-closed');
    }

    /**
     * Ouvre les inscriptions, depuis un tournoi verrouillé comme depuis un
     * tournoi dont les inscriptions ont été closes.
     *
     * C'est le geste que le comité cherchait sous le nom « Publier » : c'est lui
     * qui rend le tournoi visible et inscriptible côté membre. Il n'existait pas
     * — le passage verrouillé → publié était un effet de bord de la première
     * invitation envoyée, que rien n'annonçait (issue #35).
     */
    public function confirmOpenRegistrations(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        if (! in_array($tournament->status, [TournamentStatusEnum::LOCKED, TournamentStatusEnum::SETUP], true)) {
            $this->showOpenRegistrationsModal = false;

            return;
        }

        (new TournamentStateMachine($tournament))->publish();

        unset($this->currentTournament, $this->registrationsOpen, $this->canOpenRegistrations);
        $this->showOpenRegistrationsModal = false;
        $this->success(__('Registrations are now open.'), icon: 'o-lock-open');
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

    // ── Computed: current tournament model

    #[Computed]
    public function currentTournament(): ?Tournament
    {
        return $this->tournamentId ? Tournament::find($this->tournamentId) : null;
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
            ->map(fn ($row): array => [
                'id' => $row->id,
                'count' => $row->user_count,
                'sent_at' => $row->sent_at,
                'status' => __('Sent'),
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

        return $this->currentTournament?->state()->hasLockedContract() ?? false;
    }

    #[Computed]
    public function isLaunched(): bool
    {
        return $this->currentTournament?->state()->hasBeenLaunched() ?? false;
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
            $forfeited = app(App\Services\TournamentMatchService::class)
                ->forfeitPoolMatchesForPlayer($tournament, $user);
        }

        unset($this->registrations);

        $msg = $forfeited > 0
            ? __('No-show recorded. :count pool match(es) forfeited.', ['count' => $forfeited])
            : __('No-show recorded.');

        $this->warning($msg, icon: 'o-no-symbol');
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
            ->map(fn ($matches, $poolId): array => [
                'name' => $matches->first()->pool?->name ?? "Pool {$poolId}",
                'matches' => $matches->map(fn ($m): array => [
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

        /*
         * L'assiduité pèse sur la décision : inviter quelqu'un qui n'est jamais
         * venu n'est pas la même chose que relancer un habitué. Un seul
         * withCount, pas une requête par ligne.
         */
        $query->withCount(['tournaments as tournaments_played_count' => fn ($q) => $q
            ->where('tournament_user.registration_status', 'confirmed')]);

        return $query->get()
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->full_name,
                'email' => $u->email,
                'ranking' => $u->ranking->getLabel(),
                'played' => $u->tournaments_played_count ?? 0,
            ])
            ->toArray();
    }

    // ── Lifecycle

    public function mount(?Tournament $tournament = null): void
    {
        $this->tournamentDate = today()->addWeek()->format('Y-m-d');

        /*
         * Les libellés étaient écrits en français dans la déclaration de propriété,
         * là où __() n'est pas encore résolu. Ils se construisent donc ici.
         */
        $this->poolSizeOptions = array_map(
            fn (int $size): array => [
                'id' => $size,
                'name' => trans_choice('{1} :count player|[2,*] :count players', $size, ['count' => $size]),
            ],
            [3, 4, 5, 6],
        );

        if ($tournament !== null) {
            $this->tournamentId = $tournament->id;
            $this->name = $tournament->name;
            $this->description = $tournament->description ?? '';
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
            $this->maxUsersManual = $tournament->max_users > 0
                && $tournament->max_users !== $tournament->nb_pools * $tournament->pool_size;
            $this->price = (float) ($tournament->price ?? 0);
            $this->selectedObjective = $tournament->objective?->value ?? '';
            $this->selectedRooms = $tournament->rooms->pluck('id')->toArray();
            $this->nb_tables = (int) $tournament->rooms->sum('total_playable_tables') ?: 8;

            /*
             * Le statut ne choisit l'étape que si l'URL n'en porte pas une : l'icône
             * réglages de la liste pointe vers ?step=1 et doit ouvrir la configuration,
             * pas l'étape déduite du statut.
             */
            if ($this->step === '') {
                $this->step = match ($tournament->status) {
                    TournamentStatusEnum::LOCKED => '4',
                    TournamentStatusEnum::PUBLISHED => '4',
                    TournamentStatusEnum::SETUP => '5',
                    TournamentStatusEnum::PENDING,
                    TournamentStatusEnum::CLOSED => '6',
                    default => '1',
                };
            }

            $this->initEventPost($tournament->eventPost, $tournament->name);
        }

        // Aucune étape demandée et aucun statut pour en déduire une : on commence au début.
        if ($this->step === '') {
            $this->step = '1';
        }

        // Pre-fill location from the first room's address if not already set
        if ($this->eventLocation === '') {
            $roomId = $this->selectedRooms[0] ?? null;
            $room = $roomId
                ? Room::find($roomId)
                : Room::first();

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
        if ($this->selectedRooms === []) {
            return $this->nb_tables;
        }

        $total = Room::whereIn('id', $this->selectedRooms)->sum('total_playable_tables');

        return (int) ($total ?: $this->nb_tables);
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
            'name' => $user->full_name,
            'reference' => $payment->reference,
            'amount_due' => $payment->amount_due,
            'iban' => Club::ourClub()->first()->bank_account,
            'bic' => Club::ourClub()->first()->bic,
            'beneficiary' => 'CTT Ottignies-Blocry ASBL',
        ];
        $this->qrCodeData = (new GeneratePaymentQR)($payment);
        $this->paymentActionUserId = $userId;
        $this->showQrModal = true;
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

    #[Computed]
    public function pairs(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        return TournamentPair::where('tournament_id', $this->tournamentId)
            ->with(['player1', 'player2'])
            ->get()
            ->map(fn (TournamentPair $p): array => [
                'id' => $p->id,
                'name' => $p->displayName(),
                'p1_id' => $p->player1_id,
                'p1_name' => $p->player1?->full_name ?? '?',
                'p2_id' => $p->player2_id,
                'p2_name' => $p->player2?->full_name ?? '?',
            ])
            ->toArray();
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
                ->mapWithKeys(fn (Pool $pool): array => [
                    $pool->id => [
                        'name' => $pool->name,
                        'players' => $pool->pairs->map(fn ($pair): array => [
                            'id' => $pair->id,
                            'name' => $pair->displayName(),
                            'rank' => $pair->rankingLabel(),
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
            ->mapWithKeys(fn (Pool $pool): array => [
                $pool->id => [
                    'name' => $pool->name,
                    'players' => $pool->users->map(fn (User $u): array => [
                        'id' => $u->id,
                        'name' => $u->full_name,
                        'rank' => $u->ranking->getLabel(),
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

        (new TournamentStateMachine($tournament))->start();

        // Populate table_tournament pivot from the tournament's linked rooms
        $tableIds = Table::whereHas('room', fn ($q) => $q->whereIn('rooms.id', $tournament->rooms()->pluck('rooms.id')))
            ->pluck('id');

        $tournament->tables()->sync(
            $tableIds->mapWithKeys(fn ($id): array => [$id => ['is_table_free' => true]])->all()
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

        return User::active()
            ->whereNotIn('id', $alreadyRegistered)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get()
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->full_name . ' (' . $u->ranking->getLabel() . ')',
            ])
            ->toArray();
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
        } catch (LogicException $e) {
            $this->error($e->getMessage());

            return;
        }

        unset($this->registrations, $this->waitlist, $this->members);

        $this->memberToRegister = 0;
        $this->showRegisterModal = false;
        $this->success($user->full_name . ' ' . __('has been registered.'));
    }

    // ── Computed: registration status

    #[Computed]
    public function registrationClosed(): bool
    {
        return $this->currentTournament?->state()->canCreatePools() ?? false;
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

        $rows = $users->map(fn (User $u): array => [
            'id' => $u->id,
            'name' => $u->full_name,
            'ranking' => $u->ranking->getLabel(),
            'status' => $u->pivot->registration_status,
            'has_paid' => (bool) $u->pivot->has_paid || isset($paidPaymentIds[$u->pivot->payment_id]),
            'qr_confirmed' => (bool) $u->pivot->qr_confirmed,
            'payment_id' => $u->pivot->payment_id,
            'payment_deadline' => $u->pivot->payment_deadline,
            'registered_at' => $u->pivot->created_at,
        ]);

        return $dir === 'asc'
            ? $rows->sortBy($col)->values()
            : $rows->sortByDesc($col)->values();
    }

    /** Le tournoi est-il ouvert aux inscriptions, c'est-à-dire visible du membre ? */
    #[Computed]
    public function registrationsOpen(): bool
    {
        return $this->currentTournament?->state()->canRegisterUsers() ?? false;
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

    public function render(): mixed
    {
        $search = strtolower($this->memberSearch);
        $filteredMembers = $search === '' || $search === '0'
            ? $this->members
            : array_values(array_filter(
                $this->members,
                fn (array $m): bool => str_contains(strtolower($m['name']), $search)
                    || str_contains(strtolower($m['email'] ?? ''), $search)
            ));

        return $this->view([
            'filteredMembers' => $filteredMembers,
        ]);
    }

    // ── Hooks

    public function resetMaxUsersToStructure(): void
    {
        $this->maxUsersManual = false;
        $this->suggestMaxUsers();
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
            'description' => 'nullable|string|max:2000',
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
                'description' => $this->description ?: null,
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
        if ($logisticsChanged !== [] && $this->hasRegisteredUsers) {
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

    /**
     * Vider la sélection de membres.
     *
     * Nom imposé par x-admin.shared.selection-pill, que la liste des tournois
     * utilise déjà : le comité retrouve le même geste des deux côtés.
     */
    public function clearSelection(): void
    {
        $this->selectNoMembers();
    }

    public function selectNoMembers(): void
    {
        $this->selectedMembers = [];
    }

    public function sendInvitations(): void
    {
        if ($this->selectedMembers === [] || ! $this->tournamentId) {
            return;
        }

        if ($this->registration_deadline === '' || $this->registration_deadline === '0') {
            $this->error(__('A registration deadline is required before sending invitations.'));

            return;
        }

        $tournament = Tournament::findOrFail($this->tournamentId);

        // Inviter quelqu'un à s'inscrire à un tournoi fermé n'a pas de sens : le
        // membre suit le lien et ne peut rien faire. L'ouverture est désormais un
        // geste à part entière, nommé, et c'est un prérequis.
        if (! $tournament->registrationsAreOpen()) {
            $this->error(__('Open the registrations first — members cannot sign up yet.'));

            return;
        }
        $users = User::whereIn('id', $this->selectedMembers)->get();

        $includeArticle = $this->inviteIncludeArticle && $this->eventPostId !== null;

        /*
         * Fanned out over the `invitations` limiter rather than sent in the
         * request. Inviting the whole club used to be a hundred and forty three
         * messages leaving as fast as the worker drained them, which is the
         * burst that gets a sender classed as a spammer — and the one mass
         * mailing in the application that had never been throttled. Batched, so
         * the send has a name in the queue rather than being a hundred loose
         * jobs.
         */
        Bus::batch(
            $users->map(fn (User $user): SendTournamentInvitationJob => new SendTournamentInvitationJob(
                tournamentId: $tournament->id,
                userId: $user->id,
                customMessage: $this->inviteMessage,
                includeArticleLink: $includeArticle,
                newsPostId: $this->inviteIncludeArticle ? $this->eventPostId : null,
            ))->all()
        )->name('invitations')->dispatch();

        DB::table('tournament_invitations')->insert([
            'tournament_id' => $this->tournamentId,
            'user_count' => $users->count(),
            'message' => $this->inviteMessage ?: null,
            'include_article' => $includeArticle,
            'sent_at' => now(),
        ]);

        // Le vocabulaire des convocations : elles sont en file, pas parties.
        $this->success(
            title: __('Invitations queued — members will receive them shortly'),
            description: __(':count invitation(s) on their way.', ['count' => $users->count()]),
            icon: 'o-paper-airplane',
        );

        $this->showInviteModal = false;
        $this->inviteMessage = '';
        $this->selectedMembers = [];
        unset($this->invitationHistory);
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

    public function toggleMember(int $id): void
    {
        if (in_array($id, $this->selectedMembers)) {
            $this->selectedMembers = array_values(
                array_filter($this->selectedMembers, fn ($m): bool => $m !== $id)
            );
        } else {
            $this->selectedMembers[] = $id;
        }
    }

    #[Computed]
    public function unpaired(): array
    {
        if (! $this->tournamentId) {
            return [];
        }

        $pairedIds = TournamentPair::where('tournament_id', $this->tournamentId)
            ->get()
            ->flatMap(fn ($p): array => [$p->player1_id, $p->player2_id])
            ->unique()
            ->toArray();

        return Tournament::findOrFail($this->tournamentId)
            ->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->whereNotIn('users.id', $pairedIds)
            ->get()
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    public function updatedMatchType(): void
    {
        $this->markPoolsStaleIfGenerated();
    }

    public function updatedMaxUsers(): void
    {
        $this->maxUsersManual = true;
    }

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
        if ($this->selectedRooms !== []) {
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

    public function validateAndLock(): void
    {
        if (! $this->tournamentId) {
            return;
        }

        if ($this->name === '' || $this->name === '0' || ($this->registration_deadline === '' || $this->registration_deadline === '0')) {
            $this->error(__('Tournament name and registration deadline are required before locking.'));

            return;
        }

        (new TournamentStateMachine(Tournament::findOrFail($this->tournamentId)))->lock();

        unset($this->currentTournament, $this->isContractLocked, $this->registrationsOpen, $this->canOpenRegistrations);

        $this->step = '4';
        $this->success(__('Tournament validated! Name and price are now locked.'), icon: 'o-lock-closed');
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
            ->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->full_name,
                'ranking' => $u->ranking->getLabel(),
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

    // ── Render

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Tournaments Wizard'));
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
            'model' => $tournament,
            'type' => ClubEventTypeEnum::TOURNAMENT,
            'icon' => '🏆',
            'event_date' => $tournament->start_date->toDateString(),
            'start_time' => $startTime?->format('H:i:s') ?? '00:00:00',
            'end_time' => $endTime?->format('H:i:s'),
            'price' => (string) $tournament->price,
            'max_participants' => $tournament->max_users ?: null,
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

    /**
     * Aligne le plafond d'inscriptions sur la structure, sauf saisie manuelle.
     *
     * L'ancienne garde comparait `maxUsers` à la capacité *nouvelle* alors que
     * son commentaire annonçait l'ancienne : dès la première modification de la
     * structure, les deux différaient et la valeur restait figée sur celle de la
     * configuration précédente.
     */
    private function suggestMaxUsers(): void
    {
        if ($this->maxUsersManual) {
            return;
        }

        $this->maxUsers = $this->nb_poules * $this->pool_size;
    }
};
