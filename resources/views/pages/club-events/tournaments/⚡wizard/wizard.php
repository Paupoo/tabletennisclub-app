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
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentWaitlistRemovedNotification;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPairingService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Competitions\Tournament\Services\TournamentSimulator;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Enums\TournamentObjectiveEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\TournamentStateMachine;
use App\Jobs\SendTournamentCancellationJob;
use App\Jobs\SendTournamentInvitationJob;
use App\Jobs\SendTournamentUpdateJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasEventPostForm;
use App\Livewire\Concerns\KeepsNumericPropertiesTyped;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs;
    use HasEventPostForm, KeepsNumericPropertiesTyped, Toast, WithFileUploads;

    /** The icons of the three absences, shared by their buttons and their badges. */
    public const string ICON_ABSENT = 'o-user-minus';

    public const string ICON_EXCUSED = 'o-hand-raised';

    public const string ICON_UNEXCUSED = 'o-exclamation-triangle';

    /** Places in the bracket: 4, 8 or 16. */
    public int $bracketSize = 8;

    public bool $bulkCancelModal = false;

    public bool $bulkDrawer = false;

    /** Le relèvement du plafond a été confirmé : `save()` ne redemande pas. */
    public bool $capacityConfirmed = false;

    public bool $capacityModal = false;

    public string $description = '';

    public bool $deuceEnabled = true;

    public string $doublesRegistrationMode = 'club';

    public bool $hasHandicapPoints = true;

    public bool $inviteIncludeArticle = false;

    public string $inviteMessage = '';

    /**
     * The tables put up for the day, ticked at the launch.
     *
     * @var list<int>
     */
    public array $launchTableIds = [];

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
    public ?int $paymentActionUserId = null;

    public int $pool_size = 4;

    // ── Pools staleness flag
    public bool $poolsStale = false;

    // ── Frais d'inscription (0 = gratuit)
    public float $price = 0;

    public bool $publicRegistration = false;

    public string $qrCodeData = '';

    public array $qrPaymentDetails = [];

    /** Vide = pas de tarif réduit. Une chaîne, pour qu'un champ vidé reste vide. */
    public string $reducedPrice = '';

    public string $reducedPriceUnderAge = '';

    public string $registration_deadline = '';

    /** What the list of registrations searches, by first or last name. */
    public string $registrationSearch = '';

    /** The tab of the list of registrations: all, to_check, present, absent or unpaid. */
    public string $registrationTab = 'all';

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

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

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
        $this->bracketSize = $this->fittingBracketSize();

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
        return Room::with('tables:id,room_id,state')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => $room->id,
                'name' => $room->name . ' (' . $room->usableTournamentCapacity() . ' tables)',
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
            new TournamentStateMachine($tournament)->cancel();
        } catch (LogicException) {
            $this->showCancelModal = false;
            $this->error(__('Matches have already been played: this tournament can no longer be cancelled.'));

            return;
        } catch (InvalidArgumentException) {
            $this->showCancelModal = false;
            $this->error(__('This tournament is already over, so there is nothing left to cancel.'));

            return;
        }

        // Fanned out over the `convocations` limiter, like every other mailing
        // that goes to more than one member. A full draw is sixty four notices.
        $players = $tournament->users()
            ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered', 'waiting'])
            ->get();

        if ($players->isNotEmpty()) {
            Bus::batch(
                $players->map(fn (User $player): SendTournamentCancellationJob => new SendTournamentCancellationJob(
                    tournamentId: $tournament->id,
                    userId: $player->id,
                ))->all()
            )->name('tournament-cancellation')->dispatch();
        }

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

        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations);
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

        $tournament = Tournament::findOrFail($this->tournamentId);
        $service = app(TournamentService::class);

        try {
            foreach (User::whereIn('id', $this->selectedPeople)->get() as $player) {
                $service->markAbsent($tournament, $player, excused: true);
            }
        } catch (LogicException) {
            $this->error(__('The tournament is launched: record a walkover from the live centre instead.'));

            return;
        }

        $this->resetPostAction();
        unset($this->pairBoard);
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

    /**
     * Confirmation d'un plafond relevé sur un tournoi qui a une liste d'attente.
     */
    public function confirmCapacityChange(): void
    {
        $this->capacityConfirmed = true;
        $this->capacityModal = false;

        $this->save();
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

        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations);
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
            new TournamentStateMachine($tournament)->setUp();
        } catch (InvalidArgumentException) {
            $this->showCloseRegistrationsModal = false;
            $this->error(__('Nobody has registered yet, so there are no registrations to close. Cancel the tournament instead.'));

            return;
        }

        // Ceux qui attendent encore n'auront pas de place : ils sont prévenus.
        app(TournamentService::class)->closeWaitlist($tournament);

        unset($this->currentTournament, $this->waitlist, $this->registrationRows, $this->registrationTabCounts, $this->registrations, $this->registrationsOpen, $this->canOpenRegistrations);
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

        new TournamentStateMachine($tournament)->publish();

        unset($this->currentTournament, $this->registrationsOpen, $this->canOpenRegistrations);
        $this->showOpenRegistrationsModal = false;
        $this->success(__('Registrations are now open.'), icon: 'o-lock-open');
    }

    public function confirmPresence(int $userId): void
    {
        if (! $this->tournamentId) {
            return;
        }

        $isBack = app(TournamentService::class)->markPresent(
            Tournament::findOrFail($this->tournamentId),
            User::findOrFail($userId),
        );

        if (! $isBack) {
            $this->error(__('The pools are drawn: this player can no longer be brought back.'));

            return;
        }

        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations, $this->pairBoard);
        $this->success(__('Presence confirmed.'));
    }

    // ── Pair management (doubles)

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

            // Un joueur resté sur le banc serait écarté des poules sans bruit,
            // alors qu'il est inscrit — et souvent déjà payé.
            $benchCount = app(TournamentPairingService::class)->bench($tournament)->count();
            if ($benchCount > 0) {
                $this->error(trans_choice(
                    '1 player has no partner: pair them or cancel their registration.|:count players have no partner: pair them or cancel their registration.',
                    $benchCount,
                    ['count' => $benchCount],
                ));

                return;
            }
        } elseif ($tournament->users()->wherePivotIn('registration_status', ['registered', 'confirmed'])->count() === 0) {
            $this->error(__('No registered players.'));

            return;
        }

        // Le tirage suit la configuration enregistrée : un nombre de poules
        // saisi sans être enregistré ne doit pas tirer autre chose que ce que
        // le tableau final attendra.
        $entrants = $tournament->match_type === 'double'
            ? $tournament->pairs()->count()
            : $tournament->users()->wherePivotIn('registration_status', ['registered', 'confirmed'])->count();

        if (intdiv($entrants, max(1, $tournament->nb_pools)) < 3) {
            $this->error(__('Every pool needs at least three entrants: :count for :pools pools. Use fewer pools.', [
                'count' => $entrants,
                'pools' => $tournament->nb_pools,
            ]));

            return;
        }

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, $tournament->nb_pools);

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

    /**
     * The tables of the chosen rooms, for the checklist of the launch.
     *
     * @return list<array{id: int, name: string, out_of_service: bool}>
     */
    #[Computed]
    public function launchableTables(): array
    {
        return Table::with('room')
            ->whereIn('room_id', $this->selectedRooms)
            ->orderBy('room_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Table $table): array => [
                'id' => $table->id,
                'name' => ($table->room?->name ?? '—') . ' · ' . __('Table') . ' ' . $table->name,
                'out_of_service' => $table->state === TableStateEnum::OUT_OF_SERVICE,
            ])
            ->all();
    }

    /**
     * The roll call: a registered player is not here.
     *
     * `$excused` says whether they had warned, which decides what becomes of
     * an unpaid entry fee.
     */
    public function markAbsent(int $userId, bool $excused): void
    {
        if (! $this->tournamentId) {
            return;
        }

        try {
            app(TournamentService::class)->markAbsent(
                Tournament::findOrFail($this->tournamentId),
                User::findOrFail($userId),
                $excused,
            );
        } catch (LogicException) {
            $this->error(__('The tournament is launched: record a walkover from the live centre instead.'));

            return;
        }

        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations, $this->pairBoard, $this->poolsGenerated, $this->pools);

        $this->warning($excused ? __('Excused absence recorded.') : __('Unexcused absence recorded: the entry fee stays due.'), icon: 'o-no-symbol');
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

        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations);
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

    /**
     * How far the tournament lets the committee go in the steps.
     */
    public function maxReachableStepIndex(): int
    {
        $keys = array_column($this->wizardSteps(), 'key');

        $reachable = match (true) {
            in_array($this->currentTournament?->status, [TournamentStatusEnum::SETUP, TournamentStatusEnum::PENDING, TournamentStatusEnum::CLOSED], true) => '6',
            $this->currentTournament?->status === TournamentStatusEnum::PUBLISHED => in_array('pairs', $keys, true) ? 'pairs' : '5',
            $this->currentTournament?->status === TournamentStatusEnum::LOCKED => '4',
            $this->tournamentId !== null => '3',
            default => '1',
        };

        return (int) array_search($reachable, $keys, true);
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
                // Opting out of "new tournaments" now reaches the invitation:
                // opening the registrations announces nothing any more.
                'declinesInvitations' => ! $u->wantsNotification('new_tournaments'),
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
            $this->bracketSize = $tournament->bracket_size ?? $this->fittingBracketSize();
            $this->maxUsers = $tournament->max_users;
            $this->maxUsersManual = $tournament->max_users > 0
                && $tournament->max_users !== $tournament->nb_pools * $tournament->pool_size * $this->playersPerEntrant();
            $this->price = (float) ($tournament->price ?? 0);
            $this->reducedPrice = $tournament->reduced_price_under_age === null ? '' : (string) $tournament->reduced_price;
            $this->reducedPriceUnderAge = (string) ($tournament->reduced_price_under_age ?? '');
            $this->selectedObjective = $tournament->objective?->value ?? '';
            $this->selectedRooms = $tournament->rooms->pluck('id')->toArray();
            $this->launchTableIds = $this->playableTableIds();
            $this->nb_tables = count($this->launchTableIds) ?: 8;

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

    /**
     * Drop a player on another one, or on the bench (`$targetId` null).
     */
    public function movePlayer(int $playerId, ?int $targetId = null): void
    {
        $player = $this->entrant($playerId);
        $target = $targetId === null ? null : $this->entrant($targetId);

        if (! $player instanceof User || ($targetId !== null && ! $target instanceof User)) {
            return;
        }

        $this->changePairs(fn (TournamentPairingService $pairing, Tournament $tournament) => $pairing->move($tournament, $player, $target, Auth::user()));
    }

    // ── Computed: effective table count

    #[Computed]
    public function nbTables(): int
    {
        if ($this->selectedRooms === []) {
            return $this->nb_tables;
        }

        return count($this->playableTableIds()) ?: $this->nb_tables;
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

    /**
     * @return array{pairs: list<array{id: int, players: list<array{id: int, name: string, ranking: string, age: int|null}>}>, bench: list<array{id: int, name: string, ranking: string, age: int|null}>}
     */
    #[Computed]
    public function pairBoard(): array
    {
        return $this->currentTournament instanceof Tournament
            ? app(TournamentPairingService::class)->board($this->currentTournament)
            : ['pairs' => [], 'bench' => []];
    }

    /**
     * De combien d'inscrits actifs le tournoi dépasserait le plafond saisi.
     *
     * Personne n'est désinscrit : abaisser le plafond ne touche à rien en base.
     * L'organisateur qui réduit la structure doit seulement savoir qu'il laisse
     * le tournoi en surcapacité, plutôt que de le découvrir le jour même.
     */
    #[Computed]
    public function pendingOverCapacity(): int
    {
        $tournament = $this->currentTournament;

        if (! $tournament instanceof Tournament || $this->maxUsers <= 0) {
            return 0;
        }

        return max(0, $tournament->activeRegistrationsCount() - $this->maxUsers);
    }

    /**
     * Combien de personnes en attente recevraient une offre si on enregistrait.
     *
     * Lu avant l'écriture : `maxUsers` est la valeur du formulaire, pas encore
     * celle du tournoi. C'est ce qui permet de prévenir avant que les mails
     * partent — d'autant que `suggestMaxUsers()` relève le plafond tout seul
     * dès qu'on touche à la structure des poules, sans que personne n'ait saisi
     * le champ.
     */
    #[Computed]
    public function pendingWaitlistOffers(): int
    {
        $tournament = $this->currentTournament;

        if (! $tournament instanceof Tournament || ! $tournament->registrationsAreOpen()) {
            return 0;
        }

        $waiting = $tournament->users()
            ->wherePivot('registration_status', 'waiting')
            ->count();

        if ($waiting === 0) {
            return 0;
        }

        // Un plafond à 0 vaut « illimité » : plus rien ne justifie une file.
        if ($this->maxUsers <= 0) {
            return $waiting;
        }

        return min($waiting, max(0, $this->maxUsers - $tournament->activeRegistrationsCount()));
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

    /**
     * The pool sizes on offer, counted in pairs for doubles.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function poolSizeOptions(): array
    {
        return array_map(
            fn (int $size): array => [
                'id' => $size,
                'name' => $this->matchType === 'double'
                    ? trans_choice('{1} :count pair|[2,*] :count pairs', $size, ['count' => $size])
                    : trans_choice('{1} :count player|[2,*] :count players', $size, ['count' => $size]),
            ],
            [3, 4, 5, 6],
        );
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

        // Les tables du jour sont celles cochées : la salle en compte souvent
        // plus qu'on n'en monte, et une table hors service n'a rien à y faire.
        $tableIds = Table::whereIn('id', $this->launchTableIds)
            ->whereNot('state', TableStateEnum::OUT_OF_SERVICE)
            ->pluck('id');

        if ($tableIds->isEmpty()) {
            $this->showLaunchModal = false;
            $this->error(__('Tick at least one table for the day.'));

            return null;
        }

        new TournamentStateMachine($tournament)->start();

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
        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations);
        unset($this->waitlist);

        $this->success($user->full_name . ' ' . __('has been moved to the registered list.'));
    }

    public function proposePairs(): void
    {
        $this->changePairs(fn (TournamentPairingService $pairing, Tournament $tournament) => $pairing->propose($tournament, Auth::user()));
    }

    public function publishPairs(): void
    {
        if (! $this->currentTournament instanceof Tournament) {
            return;
        }

        app(TournamentPairingService::class)->publish($this->currentTournament);

        unset($this->currentTournament);
        $this->success(__('Pairs published: each player is told who they play with.'), icon: 'o-paper-airplane');
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
            app(TournamentService::class)->registerUser($tournament, $user, byCommittee: true);
        } catch (LogicException $e) {
            $this->error($e->getMessage());

            return;
        }

        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations, $this->waitlist, $this->members, $this->pairBoard);

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

    /**
     * Every active registration, with the tabs it belongs to.
     *
     * An absence excused has its fee dropped: it owes nothing, so it is not
     * unpaid.
     */
    #[Computed]
    public function registrationRows(): Collection
    {
        if (! $this->tournamentId) {
            return collect();
        }

        $tournament = Tournament::findOrFail($this->tournamentId);
        $users = $tournament
            ->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered', 'no_show'])
            ->get();

        $paymentIds = $users->map(fn (User $u) => $u->pivot->payment_id)->filter()->unique()->values();
        $paidPaymentIds = Payment::whereIn('id', $paymentIds)->where('status', 'paid')->pluck('id')->flip();
        $isPaid = $tournament->isPaid();

        return $users->map(function (User $u) use ($paidPaymentIds, $isPaid): array {
            $status = $u->pivot->registration_status;
            $hasPaid = (bool) $u->pivot->has_paid || isset($paidPaymentIds[$u->pivot->payment_id]);
            $owes = $isPaid && ! $hasPaid && ($status !== 'no_show' || $u->pivot->absence_excused !== true);

            return [
                'id' => $u->id,
                'name' => $u->full_name,
                'sort_name' => Str::lower(Str::ascii($u->first_name . ' ' . $u->last_name)),
                'search' => Str::lower(Str::ascii($u->first_name . ' ' . $u->last_name)),
                'ranking' => $u->ranking->getLabel(),
                'status' => $status,
                'has_paid' => $hasPaid,
                'qr_confirmed' => (bool) $u->pivot->qr_confirmed,
                'payment_id' => $u->pivot->payment_id,
                'payment_deadline' => $u->pivot->payment_deadline,
                'registered_at' => $u->pivot->created_at,
                'status_badge' => $this->statusBadge($status, $u->pivot->absence_excused, $hasPaid),
                'tabs' => array_values(array_filter([
                    in_array($status, ['registered', 'spot_offered'], true) ? 'to_check' : null,
                    $status === 'confirmed' ? 'present' : null,
                    $status === 'no_show' ? 'absent' : null,
                    $owes ? 'unpaid' : null,
                ])),
            ];
        });
    }

    // ── Computed: active registrations (not waiting, not cancelled)

    /**
     * The registered players as the list shows them: the tab chosen, the name
     * searched, sorted by first name then last name unless a column says
     * otherwise.
     */
    #[Computed]
    public function registrations(): Collection
    {
        $col = $this->sortBy['column'] === 'name' ? 'sort_name' : $this->sortBy['column'];
        $search = Str::lower(Str::ascii(trim($this->registrationSearch)));

        $rows = $this->registrationRows
            ->filter(fn (array $row): bool => $this->registrationTab === 'all' || in_array($this->registrationTab, $row['tabs'], true))
            ->filter(fn (array $row): bool => $search === '' || str_contains($row['search'], $search));

        return $this->sortBy['direction'] === 'asc'
            ? $rows->sortBy($col)->values()
            : $rows->sortByDesc($col)->values();
    }

    /** Le tournoi est-il ouvert aux inscriptions, c'est-à-dire visible du membre ? */
    #[Computed]
    public function registrationsOpen(): bool
    {
        return $this->currentTournament?->state()->canRegisterUsers() ?? false;
    }

    /**
     * How many players each tab of the list holds.
     *
     * @return array{all: int, to_check: int, present: int, absent: int, unpaid: int}
     */
    #[Computed]
    public function registrationTabCounts(): array
    {
        $rows = $this->registrationRows;
        $count = fn (string $tab): int => $rows->filter(fn (array $row): bool => in_array($tab, $row['tabs'], true))->count();

        return [
            'all' => $rows->count(),
            'to_check' => $count('to_check'),
            'present' => $count('present'),
            'absent' => $count('absent'),
            'unpaid' => $count('unpaid'),
        ];
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

        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations);
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

    public function resetPairs(): void
    {
        $this->changePairs(fn (TournamentPairingService $pairing, Tournament $tournament) => $pairing->resetAll($tournament));
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
            'bracketSize' => ['required', 'integer', 'in:4,8,16', function (string $attribute, mixed $value, Closure $fail): void {
                $direct = $this->nb_poules * $this->nb_qualifies;

                if ((int) $value < $direct || (int) $value > $direct + $this->nb_poules) {
                    $fail(__('A bracket of :size cannot be filled: :direct players go straight in, and at most :pools more can come from the rank below.', [
                        'size' => $value,
                        'direct' => $direct,
                        'pools' => $this->nb_poules,
                    ]));
                }
            }],
            'totalSets' => 'required|integer|min:1|max:5',
            'deuceEnabled' => 'boolean',
            'hasHandicapPoints' => 'boolean',
            'logistics_buffer' => 'required|integer|min:0|max:30',
            'matchType' => 'required|in:single,double',
            'doublesRegistrationMode' => 'nullable|in:club,self',
            'maxUsers' => ['required', 'integer', 'min:0', function (string $attribute, mixed $value, Closure $fail): void {
                if ($this->matchType === 'double' && (int) $value % 2 !== 0) {
                    $fail(__('In doubles the cap must be even: every player needs a partner.'));
                }
            }],
            'price' => 'required|numeric|min:0',
            'reducedPrice' => 'nullable|required_with:reducedPriceUnderAge|numeric|min:0|lt:price',
            'reducedPriceUnderAge' => 'nullable|required_with:reducedPrice|integer|min:1|max:99',
            'description' => 'nullable|string|max:255',
        ]);

        // Relever le plafond envoie des offres, tout de suite et sans retour
        // possible. On le dit avant, pas après — et seulement si des offres
        // partiraient réellement, si bien qu'un tournoi qu'on crée ou qui n'a
        // personne en attente ne voit jamais ce modal.
        if ($this->tournamentId && ! $this->capacityConfirmed && $this->pendingWaitlistOffers > 0) {
            $this->capacityModal = true;

            return;
        }

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
                'bracket_size' => $this->bracketSize,
                'sets_to_win' => $this->totalSets,
                'deuce_enabled' => $this->deuceEnabled,
                'has_handicap_points' => $this->hasHandicapPoints,
                'logistics_buffer_minutes' => $this->logistics_buffer,
                'match_type' => $this->matchType,
                'doubles_registration_mode' => $this->matchType === 'double' ? $this->doublesRegistrationMode : null,
                'objective' => $this->selectedObjective ?: null,
                'max_users' => $this->maxUsers,
                'price' => $this->price,
                'reduced_price' => $this->reducedPrice === '' ? null : (float) $this->reducedPrice,
                'reduced_price_under_age' => $this->reducedPriceUnderAge === '' ? null : (int) $this->reducedPriceUnderAge,
                'registration_deadline' => $this->registration_deadline ?: null,
            ]
        );

        $tournament->rooms()->sync($this->selectedRooms);

        $this->tournamentId = $tournament->id;

        // Relever le plafond ouvre des places pour de bon : la file doit être
        // appelée, sinon elles se remplissent au premier arrivé pendant que
        // ceux qui attendaient gardent leur rang pour rien. Appel
        // inconditionnel, comme côté packs d'entraînement : c'est le service
        // qui recalcule s'il y a quelque chose à offrir.
        app(TournamentService::class)->releaseSpots($tournament);

        $this->capacityConfirmed = false;

        // Notify registered players when logistical details changed.
        if ($logisticsChanged !== [] && $this->hasRegisteredUsers) {
            unset($this->hasRegisteredUsers);
            $players = $tournament->users()
                ->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered'])
                ->get();

            if ($players->isNotEmpty()) {
                Bus::batch(
                    $players->map(fn (User $player): SendTournamentUpdateJob => new SendTournamentUpdateJob(
                        tournamentId: $tournament->id,
                        userId: $player->id,
                        changes: $logisticsChanged,
                    ))->all()
                )->name('tournament-update')->dispatch();
            }
        }

        unset(
            $this->isContractLocked,
            $this->hasRegisteredUsers,
            $this->currentTournament,
            $this->pendingWaitlistOffers,
            $this->pendingOverCapacity,
            $this->waitlist,
            $this->registrationRows, $this->registrationTabCounts, $this->registrations,
        );

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
        $this->selectedMembers = $this->withoutDecliners(array_column($this->members, 'id'));
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
        $users = User::whereIn('id', $this->selectedMembers)
            ->get()
            ->filter(fn (User $user): bool => $user->wantsNotification('new_tournaments'))
            ->values();

        if ($users->isEmpty()) {
            $this->error(__('None of the selected members wishes to hear about new tournaments.'));

            return;
        }

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
            bracketSize: $this->bracketSize,
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
            $this->selectedMembers = $this->withoutDecliners([...$this->selectedMembers, $id]);
        }
    }

    public function updatedMatchType(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->suggestMaxUsers();
    }

    public function updatedMaxUsers(): void
    {
        $this->maxUsersManual = true;
    }

    public function updatedNbPoules(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->suggestMaxUsers();
        $this->bracketSize = $this->fittingBracketSize();
    }

    public function updatedNbQualifies(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->bracketSize = $this->fittingBracketSize();
    }

    public function updatedPoolSize(): void
    {
        $this->markPoolsStaleIfGenerated();
        $this->suggestMaxUsers();
    }

    /** The table's checkboxes cannot be disabled one by one: a decliner ticked there is unticked here. */
    public function updatedSelectedMembers(): void
    {
        $this->selectedMembers = $this->withoutDecliners($this->selectedMembers);
    }

    public function updatedSelectedRooms(): void
    {
        $this->launchTableIds = $this->playableTableIds();

        if ($this->launchTableIds !== []) {
            $this->nb_tables = count($this->launchTableIds);
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

        new TournamentStateMachine(Tournament::findOrFail($this->tournamentId))->lock();

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

    /**
     * The steps of this tournament, in order.
     *
     * A doubles tournament has one more, for its pairs, between the
     * registrations and the launch — pairs the players chose can be reworked
     * on the day as well.
     *
     * @return list<array{key: string, label: string, icon: string}>
     */
    public function wizardSteps(): array
    {
        $steps = [
            ['key' => '1', 'label' => __('Configuration'), 'icon' => 'o-cog-6-tooth'],
            ['key' => '2', 'label' => __('Web event'), 'icon' => 'o-globe-alt'],
            ['key' => '3', 'label' => __('Validation'), 'icon' => 'o-lock-closed'],
            ['key' => '4', 'label' => __('Invitations'), 'icon' => 'o-envelope'],
            ['key' => '5', 'label' => __('Registrations'), 'icon' => 'o-users'],
        ];

        if (($this->currentTournament?->match_type ?? $this->matchType) === 'double') {
            $steps[] = ['key' => 'pairs', 'label' => __('Pairs'), 'icon' => 'o-user-group'];
        }

        $steps[] = ['key' => '6', 'label' => __('Launch'), 'icon' => 'o-rocket-launch'];

        return $steps;
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
            'price' => $tournament->eventPostPrice(),
            'max_participants' => $tournament->max_users ?: null,
        ];
    }

    /**
     * Run a change on the pairs, refused once the pools are drawn.
     *
     * @param  Closure(TournamentPairingService, Tournament): void  $change
     */
    private function changePairs(Closure $change): void
    {
        if (! $this->currentTournament instanceof Tournament) {
            return;
        }

        try {
            $change(app(TournamentPairingService::class), $this->currentTournament);
        } catch (LogicException) {
            $this->error(__('The tournament is launched: pairs can no longer change.'));

            return;
        }

        unset($this->pairBoard, $this->poolsGenerated, $this->matchesGenerated, $this->pools, $this->matchesByPool);
    }

    /**
     * A player registered to this tournament, or null for anyone else.
     */
    private function entrant(int $userId): ?User
    {
        return $this->currentTournament?->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed'])
            ->find($userId);
    }

    /**
     * The smallest bracket the pools can fill: every direct qualifier in, and
     * no more repêchés than there are pools.
     */
    private function fittingBracketSize(): int
    {
        return app(TournamentSimulator::class)->fittingBracketSize($this->nb_poules, $this->nb_qualifies);
    }

    private function markPoolsStaleIfGenerated(): void
    {
        if ($this->poolsGenerated || $this->matchesGenerated) {
            $this->poolsStale = true;
        }
    }

    /**
     * The tables ticked by default at the launch: in each chosen room, as many
     * as it really offers a tournament — the sound ones first, then those that
     * need repair, by number.
     *
     * Counted from the tables themselves, as the rooms screen sets them up.
     *
     * @return list<int>
     */
    private function playableTableIds(): array
    {
        return Room::with('tables')
            ->whereIn('id', $this->selectedRooms)
            ->orderBy('name')
            ->get()
            ->flatMap(fn (Room $room): array => $room->tables
                ->filter(fn (Table $table): bool => $table->state->isPlayable())
                ->sortBy([
                    fn (Table $a, Table $b): int => (int) ($a->state !== TableStateEnum::GOOD) <=> (int) ($b->state !== TableStateEnum::GOOD),
                    fn (Table $a, Table $b): int => strnatcmp($a->name, $b->name),
                ])
                ->take($room->usableTournamentCapacity())
                ->pluck('id')
                ->all())
            ->values()
            ->all();
    }

    /**
     * How many registered players make one entrant of a pool: a pair holds two.
     */
    private function playersPerEntrant(): int
    {
        return $this->matchType === 'double' ? 2 : 1;
    }

    private function resetPostAction(): void
    {
        $this->selectedPeople = [];
        $this->bulkDrawer = false;
        unset($this->registrationRows, $this->registrationTabCounts, $this->registrations);
        unset($this->waitlist);
    }

    /**
     * How a registration reads in the list, with the icon of the button that set it.
     *
     * One vocabulary for both: an absence excused raises its hand, an
     * unexcused one warns, a player who had paid is simply away.
     *
     * @return array{label: string, icon: string|null, class: string}
     */
    private function statusBadge(string $status, ?bool $absenceExcused, bool $hasPaid): array
    {
        return match (true) {
            $status === 'confirmed' => ['label' => __('Present'), 'icon' => 'o-check', 'class' => 'badge-success badge-soft'],
            $status === 'spot_offered' => ['label' => __('Spot offered'), 'icon' => null, 'class' => 'badge-info badge-soft'],
            $status === 'no_show' && $absenceExcused === false && ! $hasPaid => ['label' => __('Unexcused absence'), 'icon' => self::ICON_UNEXCUSED, 'class' => 'badge-warning badge-soft'],
            $status === 'no_show' && $absenceExcused === true && ! $hasPaid => ['label' => __('Excused absence'), 'icon' => self::ICON_EXCUSED, 'class' => 'badge-ghost'],
            $status === 'no_show' => ['label' => __('Absent'), 'icon' => self::ICON_ABSENT, 'class' => 'badge-ghost'],
            default => ['label' => __('To check in'), 'icon' => null, 'class' => 'badge-ghost'],
        };
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

        $this->maxUsers = $this->nb_poules * $this->pool_size * $this->playersPerEntrant();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    private function withoutDecliners(array $ids): array
    {
        $decliners = array_column(
            array_filter($this->members, fn (array $member): bool => $member['declinesInvitations']),
            'id',
        );

        return array_values(array_diff(array_map(intval(...), $ids), $decliners));
    }
};
