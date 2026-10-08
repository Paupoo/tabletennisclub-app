<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\InviteToPayAction;
use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\AdjustTrainingCampLineAction;
use App\Actions\ClubAdmin\Subscriptions\DecideTrainingCampRequestAction;
use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingCampAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Recurrence;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingLevel;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackScheduleChangedNotification;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledNotification;
use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use App\Domains\Trainings\Services\TrainingPackProrata;
use App\Domains\Trainings\Services\TrainingRosterExport;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use App\Livewire\Concerns\GrantsInlineDiscount;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;
use Symfony\Component\HttpFoundation\StreamedResponse;

new class extends Component
{
    use GrantsInlineDiscount;
    use HasBreadcrumbs;
    use HasFilterDrawer;
    use Toast;

    // ── Ajout manuel d'un membre par le comité ────────────────────────────────
    public bool $addMemberModal = false;

    /** Le prix d'un stage pour ce membre, en euros ; vide = le prix du stage. */
    public string $addMemberPrice = '';

    public string $addMemberPriceReason = '';

    public string $addMemberStartsOn = '';

    public int $addMemberUserId = 0;

    /** Présent depuis le début du pack, encodé en retard : plein tarif. */
    public bool $addMemberWholePack = false;

    public string $campPriceAmount = '';

    // ── Prix d'un membre sur un stage ─────────────────────────────────────────
    public bool $campPriceModal = false;

    public string $campPriceReason = '';

    public int $campPriceUserId = 0;

    // ── Cancellation modal ────────────────────────────────────────────────────
    public bool $cancelModal = false;

    public string $cancelNote = '';

    public ?int $cancelTrainingId = null;

    public string $cancelType = 'FREE';

    public bool $discontinuePackModal = false;

    public string $discontinueReason = '';

    public ?int $discontinuingPackId = null;

    public bool $formAllowDiscount = true;

    public ?int $formDayOfWeek = null;

    public string $formDescription = '';

    public int $formDurationMinutes = 90;

    public bool $formEnrollmentsOpen = true;

    /** @var array<int, string> */
    public array $formExcludedDates = [];

    /** Un stage : optionnel, facturé à part, hors cotisation. */
    public bool $formIsCamp = false;

    public bool $formIsOpenEnrollment = false;

    public int $formLevel = 0;

    /** Empty string = inherit the room's training capacity. */
    public string $formMaxParticipants = '';

    public string $formName = '';

    public string $formPackEndDate = '';

    public string $formPackStartDate = '';

    // Step 3 — Price (in euros)
    public float $formPrice = 90;

    // Step 2 — Planning
    public string $formRecurrenceType = 'weekly'; // 'weekly' | 'specific_days'

    /** Les demandes d'inscription au stage passent par le comité. */
    public bool $formRequiresApproval = false;

    public int $formRoomId = 0;

    // Step 1 — Pack info
    public int $formSeasonId = 0;

    /** @var array<int, int|string> */
    public array $formSpecificDays = [];

    public string $formStartTime = '18:00';

    public int $formTrainerId = 0;

    public string $formType = '';

    // ── Actions sur le roster ─────────────────────────────────────────────────
    /** Show packs withdrawn from the offer, so they can be found and put back. */
    // ── Gestion des niveaux ───────────────────────────────────────────────────
    public bool $levelDrawer = false;

    /** @var array{id?: int|null, label: string, color: string} */
    public array $levelForm = ['id' => null, 'label' => '', 'color' => 'primary'];

    public bool $moveMemberModal = false;

    public string $moveMemberName = '';

    public int $moveMemberUserId = 0;

    public int $moveTargetPackId = 0;

    /** Ticked by default: forgetting to warn members is worse than one extra mail. */
    public bool $notifyMembersOfChange = true;

    public ?int $packId = null;

    // ── Pack drill-down ───────────────────────────────────────────────────────
    public string $packTab = 'roster';

    public bool $regenerateModal = false;

    public bool $regenerationConfirmed = false;

    public bool $removeFromRosterModal = false;

    public string $removeFromRosterName = '';

    /** `pending` ou `waiting`/`offered` : la modale ne dit pas la même chose. */
    public string $removeFromRosterStatus = '';

    public int $removeFromRosterUserId = 0;

    public ?int $selectedPackId = null;

    public bool $showAllSessions = false;

    public bool $showInactive = false;

    public string $step = '1';

    // ── View filter ───────────────────────────────────────────────────────────
    public int $viewSeasonId = 0;

    public ?int $withdrawingPackId = null;

    public bool $withdrawPackModal = false;

    // ── Wizard state ──────────────────────────────────────────────────────────
    public bool $wizardOpen = false;

    /** Accepte en une fois toutes les demandes en attente du stage ouvert. */
    public function acceptAllCampRequests(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;

        if (! $pack?->is_camp) {
            return;
        }

        $lines = SubscriptionTrainingPack::query()
            ->with('subscription.user', 'trainingPack')
            ->where('training_pack_id', $pack->id)
            ->where('status', 'pending')
            ->where('invoiced_separately', true)
            ->get();

        $decide = new DecideTrainingCampRequestAction;
        $lines->each(fn (SubscriptionTrainingPack $line) => $decide->approve($line));

        $this->forgetRoster();
        $this->success(trans_choice('{1}One request accepted.|[2,*]:count requests accepted.', $lines->count(), ['count' => $lines->count()]));
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Accepte une demande d'inscription à un stage : la place est validée et
     * la facture du stage part aussitôt.
     */
    public function acceptCampRequest(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $line = $this->campLineFor($userId);

        if ($line === null) {
            return;
        }

        try {
            (new DecideTrainingCampRequestAction)->approve($line);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->forgetRoster();
        $this->success(__('Request of :member accepted.', ['member' => $line->subscription->user->full_name]));
    }

    #[Computed]
    public function activeSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    /**
     * Membres affiliés à la saison du pack et pas encore dedans.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function addMemberOptions(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return [];
        }

        $alreadyIn = DB::table('subscription_training_pack')
            ->join('subscriptions', 'subscriptions.id', '=', 'subscription_training_pack.subscription_id')
            ->where('subscription_training_pack.training_pack_id', $pack->id)
            ->whereIn('subscription_training_pack.status', ['enrolled', 'pending', 'offered'])
            ->pluck('subscriptions.user_id');

        return User::query()
            ->whereHas('subscriptions', fn (Builder $q) => $q
                ->where('season_id', $pack->season_id)
                ->whereIn('status', ['pending', 'confirmed', 'paid'])
            )
            ->whereNotIn('id', $alreadyIn)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->last_name . ' ' . $user->first_name,
            ])
            ->toArray();
    }

    /**
     * Le pack déborderait-il si on ajoutait quelqu'un maintenant ?
     *
     * Sert à afficher l'avertissement, pas à interdire : le comité franchit le
     * plafond en connaissance de cause (décision #10).
     */
    #[Computed]
    public function addMemberOverCapacity(): bool
    {
        $pack = $this->selectedPack;

        return $pack !== null && ! $pack->hasAvailableSpot();
    }

    /**
     * Le pack ouvert a-t-il déjà commencé ?
     *
     * Avant son début, tout ajout se facture plein tarif : « présent depuis le
     * début » ne changerait rien et n'est pas proposé.
     */
    #[Computed]
    public function addMemberPackStarted(): bool
    {
        $pack = $this->selectedPack;

        return $pack !== null && $pack->pack_start_date->lt(Carbon::today());
    }

    /**
     * Inscrit un membre dans le pack ouvert, sur décision du comité.
     *
     * Court-circuite volontairement le verrou et le plafond : c'est le pendant
     * de « inscriptions closes », et le moyen de régulariser quelqu'un que la
     * feuille de présence montre là depuis des mois.
     */
    public function addMemberToPack(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $pack = $this->selectedPack;

        if (! $pack || $this->addMemberUserId === 0) {
            return;
        }

        $subscription = Subscription::where('user_id', $this->addMemberUserId)
            ->where('season_id', $pack->season_id)
            ->first();

        if (! $subscription) {
            $this->error(__('This member has no membership for the season. Affiliate them first.'));

            return;
        }

        if ($pack->is_camp) {
            $this->addMemberToCamp($pack, $subscription);

            return;
        }

        $previousAmountDue = (float) $subscription->amount_due;

        try {
            $complement = (new AddMemberToTrainingPackAction)(
                $subscription,
                $pack,
                $this->addMemberWholePack && $this->addMemberPackStarted
                    ? (new TrainingPackProrata)->wholePackStart($pack)
                    : ($this->addMemberStartsOn ?: null),
                $subscription->has_other_family_members ? 2 : 1,
            );

            // Après l'ajout : le complément est calculé sur l'écart de montant
            // dû, et la remise le rabote ensuite — comme pour la validation
            // d'une demande de pack.
            $subscription = $subscription->fresh();
            $this->applyInlineDiscount($subscription, (float) $subscription->amount_due - $previousAmountDue);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        // Réclamé une fois la remise posée : l'invitation lit le solde net.
        if ($complement instanceof Payment) {
            (new InviteToPayAction)($complement->fresh());
        }

        $this->warnWhenUnreachable($subscription->user);

        $this->addMemberModal = false;
        $this->addMemberUserId = 0;
        $this->addMemberStartsOn = '';
        $this->addMemberWholePack = false;

        unset($this->packs, $this->selectedPack, $this->addMemberOptions, $this->addMemberOverCapacity, $this->attendanceMatrix, $this->packRoster, $this->packSummary, $this->packAttendance);

        $this->success(__(':member added to :pack.', [
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'pack' => $pack->name,
        ]), icon: 'o-user-plus');
    }

    /**
     * La grille membres × séances du pack consulté.
     *
     * Douze séances par défaut : un trimestre tient à l'écran, une saison
     * entière demanderait un scroll horizontal que personne ne lit.
     *
     * @return array{sessions: list<array<string, mixed>>, members: list<array<string, mixed>>, walkIns: list<array<string, mixed>>}
     */
    #[Computed]
    public function attendanceMatrix(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['sessions' => [], 'members' => [], 'walkIns' => []];
        }

        return app(TrainingAttendanceReport::class)->matrix(
            $pack,
            $this->showAllSessions ? PHP_INT_MAX : 12,
        );
    }

    public function backToList(): void
    {
        $this->selectedPackId = null;
    }

    /**
     * La nature du pack ne bouge plus dès qu'un membre y a été inscrit.
     */
    #[Computed]
    public function campNatureLocked(): bool
    {
        return (bool) $this->editedPack?->hasEverHadEnrolments();
    }

    /**
     * The season is navigation, not a filter (DS-A): clearing the filters must
     * not send the reader back to another season than the one they are looking at.
     */
    public function clearFilters(): void
    {
        $this->showInactive = false;
    }

    public function closeWizard(): void
    {
        $this->wizardOpen = false;
        $this->resetWizardFields();
    }

    public function confirmCancel(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $training = Training::with(['trainingPack.subscriptions.user'])->findOrFail($this->cancelTrainingId);

        $type = $this->cancelType === 'CLOSED'
            ? TrainingCancellationType::CLOSED
            : TrainingCancellationType::FREE;

        $training->cancel($type, $this->cancelNote ?: null);

        // Notify enrolled members
        if ($training->trainingPack) {
            $training->trainingPack->trainees()
                ->where('emails_notifications', true)
                ->get()
                ->each->notify(new TrainingSessionCancelledNotification($training, $type, $this->cancelNote ?: null));
        }

        unset($this->sessions, $this->packSummary, $this->attendanceMatrix);
        $this->cancelModal = false;
        $this->warning(__('Session cancelled. Members have been notified.'), icon: 'o-x-circle');
    }

    /**
     * Stop the pack for good: cancel what is left, pay people back, tell them.
     */
    public function confirmDiscontinuePack(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if (! $this->discontinuingPackId) {
            return;
        }

        $pack = TrainingPack::findOrFail($this->discontinuingPackId);

        $result = (new DiscontinueTrainingPackAction)($pack, $this->discontinueReason ?: null);

        unset($this->packs);
        $this->discontinuePackModal = false;
        $this->discontinuingPackId = null;
        $this->discontinueReason = '';

        $this->warning(
            title: __('Pack stopped.'),
            description: __(':sessions session(s) cancelled, :members member(s) notified, :amount € to refund.', [
                'sessions' => $result['sessions'],
                'members' => $result['members'],
                'amount' => number_format($result['refunded'], 2),
            ]),
            icon: 'o-x-circle',
        );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    // ── Actions sur le roster ─────────────────────────────────────────────────
    //
    // Les quatre gestes sont gardés par SubscriptionsManage, pas par
    // TrainingsManage qui ouvre l'écran : sortir quelqu'un d'un pack touche à
    // l'argent d'une affiliation, et c'est la même serrure que l'écran
    // Affiliations, d'où ce parcours est copié.

    /** Déplace une place validée vers un autre pack de la même saison. */
    public function confirmMoveMember(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($this->moveMemberUserId);
        $target = $this->moveTargetPackId === 0 ? null : TrainingPack::find($this->moveTargetPackId);

        if (! $pack || ! $subscription || ! $target) {
            return;
        }

        try {
            $refundable = (new MoveMemberBetweenTrainingPacksAction)(
                $subscription,
                $pack,
                $target,
                $subscription->has_other_family_members ? 2 : 1,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $userName = $subscription->user->first_name . ' ' . $subscription->user->last_name;

        $this->warnWhenUnreachable($subscription->user);

        $this->moveMemberModal = false;
        $this->moveMemberUserId = 0;
        $this->moveTargetPackId = 0;
        $this->forgetRoster();

        if ($refundable <= 0.0) {
            $this->success(__(':user moved to :pack.', [
                'user' => $userName,
                'pack' => $target->name,
            ]), icon: 'o-arrows-right-left');

            return;
        }

        (new RequestSubscriptionRefundAction)($subscription, $refundable, __(':member has been moved to :pack, which costs less.', [
            'member' => $userName,
            'pack' => $target->name,
        ]));

        $iban = $subscription->user->iban;

        if ($iban) {
            $this->success(__(':user moved to :pack. Refund of :amount€ to be issued to :iban.', [
                'user' => $userName,
                'pack' => $target->name,
                'amount' => number_format($refundable, 2),
                'iban' => $iban,
            ]));

            return;
        }

        $this->warning(__(':user moved to :pack. Refund of :amount€ required — no IBAN on file, please handle manually.', [
            'user' => $userName,
            'pack' => $target->name,
            'amount' => number_format($refundable, 2),
        ]));
    }

    /**
     * Confirmation step for a slot change on a pack that already has sessions.
     */
    public function confirmRegeneration(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->regenerationConfirmed = true;
        $this->regenerateModal = false;

        $this->save();
    }

    public function confirmRemoveFromRoster(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($this->removeFromRosterUserId);

        if (! $pack || ! $subscription) {
            return;
        }

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();

        if ($pivot === null || $pivot->pivot->status === 'enrolled') {
            return;
        }

        $campLine = (new TrainingCampBilling)->line($subscription, $pack);

        if ($pivot->pivot->status === 'pending' && $campLine?->invoiced_separately) {
            (new DecideTrainingCampRequestAction)->reject($campLine);

            $this->removeFromRosterModal = false;
            $this->removeFromRosterUserId = 0;
            $this->forgetRoster();
            $this->success(__('Request of :member refused.', ['member' => $subscription->user->full_name]));

            return;
        }

        (new LeaveTrainingPackAction)(
            $subscription,
            $pack,
            $subscription->has_other_family_members ? 2 : 1,
        );

        $this->removeFromRosterModal = false;
        $this->removeFromRosterUserId = 0;
        $this->removeFromRosterName = '';
        $this->removeFromRosterStatus = '';
        $this->forgetRoster();

        $this->success(__(':member removed from :pack.', [
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'pack' => $pack->name,
        ]));
    }

    public function confirmWithdrawPack(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if ($this->withdrawingPackId) {
            $this->withdrawPack($this->withdrawingPackId);
        }
    }

    #[Computed]
    public function dayOptions(): array
    {
        return [
            ['id' => 1, 'name' => __('Monday')],
            ['id' => 2, 'name' => __('Tuesday')],
            ['id' => 3, 'name' => __('Wednesday')],
            ['id' => 4, 'name' => __('Thursday')],
            ['id' => 5, 'name' => __('Friday')],
            ['id' => 6, 'name' => __('Saturday')],
            ['id' => 7, 'name' => __('Sunday')],
        ];
    }

    /**
     * Supprime un niveau que rien ne référence.
     *
     * Un niveau porté par un pack ou une séance ne se supprime jamais : le
     * désactiver le retire des listes sans réécrire l'histoire des saisons
     * passées.
     */
    public function deleteLevel(int $levelId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $level = TrainingLevel::findOrFail($levelId);

        if ($level->isInUse()) {
            $this->error(__('This level is still used by a pack or a session. Retire it instead of deleting it.'));

            return;
        }

        $level->delete();

        unset($this->levelOptions, $this->levels);

        $this->success(__('Level deleted.'), icon: 'o-trash');
    }

    /**
     * How much of the club this would touch, shown before the committee confirms.
     *
     * Deliberately no euro figure: the refund owed to each member depends on the
     * multi-pack discount they lose, so any total shown here would be a guess
     * that the actual refunds then contradict. The toast reports the real total
     * once the refunds have been computed member by member.
     *
     * @return array{members: int, waiting: int, sessions: int}
     */
    #[Computed]
    public function discontinueImpact(): array
    {
        $pack = $this->discontinuingPackId ? TrainingPack::find($this->discontinuingPackId) : null;

        if (! $pack) {
            return ['members' => 0, 'waiting' => 0, 'sessions' => 0];
        }

        return [
            'members' => $pack->committedCount(),
            'waiting' => $pack->waitlistCount(),
            'sessions' => $pack->trainings()
                ->where('status', 'scheduled')
                ->where('start', '>=', Carbon::now())
                ->count(),
        ];
    }

    #[Computed]
    public function editedPack(): ?TrainingPack
    {
        return $this->packId ? TrainingPack::find($this->packId) : null;
    }

    public function editLevel(int $levelId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $level = TrainingLevel::findOrFail($levelId);

        $this->levelForm = [
            'id' => $level->id,
            'label' => $level->label,
            'color' => $level->color,
        ];
    }

    /**
     * Everybody tied to a pack of the season shown, on one sheet. The file
     * carries contact details, so it asks for what the members list asks
     * for, not only for the trainings — and saying who took it is the price
     * of letting it leave the application.
     */
    public function exportRoster(string $format, TrainingRosterExport $exporter): ?StreamedResponse
    {
        abort_unless($this->mayExportRoster, 403);

        $season = $this->viewSeason;

        if (! $season instanceof Season) {
            return null;
        }

        $payload = $exporter->export($season, $format);

        activity()
            ->causedBy(Auth::user())
            ->event('training_roster_exported')
            ->withProperties([
                'season_id' => $season->id,
                'season' => $season->name,
                'format' => $format,
            ])
            ->log('training_roster_exported');

        return response()->streamDownload(
            function () use ($payload): void {
                echo $payload['contents'];
            },
            $payload['filename'],
            ['Content-Type' => $payload['mime']],
        );
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->showInactive) {
            $chips[] = ['key' => 'showInactive', 'label' => __('Withdrawn packs shown')];
        }

        return $chips;
    }

    /**
     * The cap that applies when no explicit maximum is set, shown as the
     * placeholder so the committee sees the real limit without reading the help.
     */
    #[Computed]
    public function inheritedRoomCapacity(): ?int
    {
        return $this->formRoomId
            ? Room::find($this->formRoomId)?->capacity_for_trainings
            : null;
    }

    #[Computed]
    public function levelOptions(): array
    {
        return TrainingLevel::active()
            ->get()
            ->map(fn (TrainingLevel $level): array => ['id' => $level->id, 'name' => $level->label])
            ->toArray();
    }

    /** @return Collection<int, TrainingLevel> */
    #[Computed]
    public function levels(): Collection
    {
        return TrainingLevel::ordered()->get();
    }

    #[Computed]
    public function mayExportRoster(): bool
    {
        return Gate::allows(Permission::TrainingsView->value) && Gate::allows(Permission::UsersView->value);
    }

    /**
     * Whether the visitor builds the offer, or only reads it: the committee reads
     * every pack, its roster and its attendance at the baseline.
     */
    #[Computed]
    public function mayManage(): bool
    {
        return Gate::allows(Permission::TrainingsManage->value);
    }

    public function mount(): void
    {
        $this->viewSeasonId = Season::where('is_active', true)->value('id') ?? 0;

        // The task counter links straight to the stage whose requests wait.
        $packId = (int) request()->query('pack', 0);

        if ($packId > 0 && ($pack = TrainingPack::find($packId))) {
            $this->viewSeasonId = $pack->season_id;
            $this->openPack($pack->id);
        }
    }

    /**
     * Les packs vers lesquels ce membre peut être déplacé.
     *
     * On ne propose pas les packs retirés de l'offre : y déplacer quelqu'un
     * l'inscrirait à un entraînement que le club a cessé de proposer. Les packs
     * complets ou aux inscriptions closes, eux, restent proposés et marqués —
     * même politique que l'ajout manuel, où le comité franchit le plafond en
     * connaissance de cause.
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function moveTargetOptions(): array
    {
        $pack = $this->selectedPack;

        if (! $pack || $this->moveMemberUserId === 0) {
            return [];
        }

        $subscription = $this->rosterSubscription($this->moveMemberUserId);

        if (! $subscription) {
            return [];
        }

        $held = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->whereIn('status', ['enrolled', 'pending', 'offered'])
            ->pluck('training_pack_id');

        $options = TrainingPack::query()
            ->where('season_id', $pack->season_id)
            ->where('is_active', true)
            ->whereKeyNot($pack->id)
            ->whereNotIn('id', $held)
            // `room` autant que `level` : sans plafond propre,
            // effectiveMaxParticipants() retombe sur la capacité de la salle, et
            // hasAvailableSpot() — qui compose le libellé de chaque option — va
            // la chercher. Le chargement paresseux est interdit hors production,
            // donc l'oubli ne dégrade pas la page : il la casse.
            ->with(['level', 'room'])
            ->get()
            ->map(fn (TrainingPack $candidate): array => [
                'id' => $candidate->id,
                'packName' => $candidate->name,
                'name' => $candidate->name
                    . ' · ' . ($candidate->level?->label ?? '—')
                    . ' · ' . number_format((float) $candidate->price, 2, ',', ' ') . ' €'
                    . ($candidate->hasAvailableSpot() ? '' : ' · ' . __('Full')),
            ]);

        return LocaleSort::byKey($options, 'packName')->values()->all();
    }

    public function newLevel(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->levelForm = ['id' => null, 'label' => '', 'color' => 'primary'];
    }

    public function nextStep(): void
    {
        if ($this->step === '1') {
            $rules = [
                'formSeasonId' => 'required|integer|min:1',
                'formName' => 'required|min:2|max:255',
                'formLevel' => 'required|integer|min:1',
                'formType' => 'required',
                'formRoomId' => 'required|integer|min:1',
            ];

            if ($this->formType !== '' && $this->formType !== TrainingType::FREE->value) {
                $rules['formTrainerId'] = 'required|integer|min:1';
            }

            $this->validate($rules);
        }

        if ($this->step === '2') {
            $rules = [
                'formStartTime' => 'required',
                'formDurationMinutes' => 'required|integer|min:15|max:480',
                'formMaxParticipants' => 'nullable|integer|min:1|max:999',
            ];

            if ($this->formRecurrenceType === 'weekly') {
                $rules['formDayOfWeek'] = 'required|integer|between:1,7';
            } else {
                $rules['formSpecificDays'] = 'required|array|min:1';
            }

            if ($this->formPackStartDate || $this->formPackEndDate) {
                $rules['formPackStartDate'] = 'required|date';
                $rules['formPackEndDate'] = 'required|date|after_or_equal:formPackStartDate';
            }

            $this->validate($rules);
        }

        $this->step = (string) ((int) $this->step + 1);
    }

    public function openAddMember(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->addMemberUserId = 0;
        $this->addMemberStartsOn = '';
        $this->addMemberWholePack = false;
        $this->addMemberPrice = '';
        $this->addMemberPriceReason = '';
        $this->addMemberModal = true;

        unset($this->addMemberOptions, $this->addMemberOverCapacity);
    }

    public function openCampPrice(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $line = $this->campLineFor($userId);

        if ($line === null) {
            return;
        }

        $this->campPriceUserId = $userId;
        $this->campPriceAmount = $line->override_amount !== null ? number_format(((int) $line->override_amount) / 100, 2, '.', '') : '';
        $this->campPriceReason = (string) $line->override_reason;
        $this->campPriceModal = true;
    }

    // ── Cancellation ──────────────────────────────────────────────────────────

    public function openCancel(int $trainingId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->cancelTrainingId = $trainingId;
        $this->cancelType = 'FREE';
        $this->cancelNote = '';
        $this->cancelModal = true;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->resetWizardFields();
        $this->wizardOpen = true;
        $this->step = '1';
    }

    public function openDiscontinuePack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->discontinuingPackId = $packId;
        $this->discontinueReason = '';
        $this->discontinuePackModal = true;
    }

    public function openEdit(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $pack = TrainingPack::findOrFail($packId);

        $this->packId = $pack->id;
        $this->formSeasonId = $pack->season_id;
        $this->formName = $pack->name;
        $this->formLevel = $pack->training_level_id ?? 0;
        $this->formType = $pack->type->value;
        $this->formTrainerId = $pack->trainer_id ?? 0;
        $this->formRoomId = $pack->room_id;
        $this->formDescription = $pack->description ?? '';
        $this->formDayOfWeek = $pack->day_of_week;
        $this->formSpecificDays = $pack->days_of_week ?? [];
        $this->formRecurrenceType = empty($pack->days_of_week) ? 'weekly' : 'specific_days';
        $this->formStartTime = $pack->start_time ?? '18:00';
        $this->formDurationMinutes = $pack->duration_minutes ?? 90;
        $this->formPackStartDate = $pack->pack_start_date?->toDateString() ?? '';
        $this->formPackEndDate = $pack->pack_end_date?->toDateString() ?? '';
        $this->formExcludedDates = $pack->excluded_dates ?? [];
        $this->formPrice = (float) $pack->price;
        $this->formAllowDiscount = $pack->allow_discount;
        $this->formIsCamp = $pack->is_camp;
        $this->formRequiresApproval = $pack->requires_approval;
        $this->formMaxParticipants = (string) ($pack->max_participants ?? '');
        $this->formIsOpenEnrollment = $pack->is_open_enrollment;
        $this->formEnrollmentsOpen = $pack->enrollments_open;

        $this->wizardOpen = true;
        $this->step = '1';
    }

    /**
     * Ouvre la sortie d'une place validée : départ daté ou erreur d'encodage.
     *
     * La modale est partagée avec l'écran Affiliations, d'où le passage par un
     * événement plutôt que par un état de cet écran.
     */
    public function openLeaveMember(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $subscription = $this->rosterSubscription($userId);

        if (! $subscription || ! $this->selectedPack) {
            return;
        }

        $this->dispatch('open-training-pack-exit', subscriptionId: $subscription->id, packId: $this->selectedPack->id);
    }

    /** Ouvre le choix du pack de destination. */
    public function openMoveMember(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $this->moveMemberUserId = $userId;
        $this->moveMemberName = (string) $this->rosterSubscription($userId)?->user->full_name;
        $this->moveTargetPackId = 0;
        $this->moveMemberModal = true;
        unset($this->moveTargetOptions);
    }

    public function openPack(int $packId): void
    {
        $this->selectedPackId = $packId;
        $this->packTab = 'roster';
        unset($this->selectedPack, $this->sessions, $this->packRoster, $this->packSummary, $this->packAttendance);
    }

    /**
     * Écarte une demande, ou retire quelqu'un de la file d'attente.
     *
     * Sans confirmation, et c'est voulu : {@see LeaveTrainingPackAction} détache
     * purement et simplement tout ce qui n'est pas `enrolled` — aucune date de
     * sortie, aucun euro, aucune trace. Une place validée, elle, passe par la
     * modale, parce qu'elle peut rendre de l'argent.
     */
    /** Ouvre la confirmation de retrait d'une demande ou d'une place en file. */
    public function openRemoveFromRoster(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($userId);

        if (! $pack || ! $subscription) {
            return;
        }

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();

        if ($pivot === null || $pivot->pivot->status === 'enrolled') {
            return;
        }

        $this->removeFromRosterUserId = $userId;
        $this->removeFromRosterName = (string) $subscription->user->full_name;
        $this->removeFromRosterStatus = (string) $pivot->pivot->status;
        $this->removeFromRosterModal = true;
    }

    public function openWithdrawPack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->withdrawingPackId = $packId;
        $this->withdrawPackModal = true;
    }

    /**
     * Le pointage d'un pack, ramené à ce qu'une fiche a besoin d'afficher.
     *
     * Deux requêtes, pas deux par membre : {@see TrainingAttendanceReport::memberRate()}
     * en fait deux à chaque appel, et la fiche l'appellerait une fois par ligne.
     *
     * Le dénominateur est le nombre de séances **pointées** du pack, le même pour
     * tout le monde — c'est la définition du domaine, et la seule qui permette de
     * comparer deux lignes entre elles. Un membre sans aucune ligne de pointage
     * est donc à 0 %, ce qui est exact : il n'est venu à aucune séance pointée.
     *
     * @return array{counted: int, present: array<int, int>}
     */
    #[Computed]
    public function packAttendance(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['counted' => 0, 'present' => []];
        }

        $counted = Training::query()
            ->where('training_pack_id', $pack->id)
            ->where('status', 'scheduled')
            ->whereNotNull('attendance_taken_at')
            ->count();

        if ($counted === 0) {
            return ['counted' => 0, 'present' => []];
        }

        $present = DB::table('training_user')
            ->join('trainings', 'trainings.id', '=', 'training_user.training_id')
            ->where('trainings.training_pack_id', $pack->id)
            ->where('trainings.status', 'scheduled')
            ->whereNotNull('trainings.attendance_taken_at')
            ->where('training_user.status', 'present')
            ->groupBy('training_user.user_id')
            ->pluck(DB::raw('COUNT(*)'), 'training_user.user_id')
            ->map(intval(...))
            ->all();

        return ['counted' => $counted, 'present' => $present];
    }

    /**
     * Qui est dans le pack, et à quel titre.
     *
     * Quatre listes plutôt qu'une colonne « statut » : le comité ne se pose pas
     * la même question devant un inscrit, une demande à valider, une file
     * d'attente et quelqu'un qui est parti. Les mélanger obligeait à trier des
     * yeux la seule ligne qu'on cherchait.
     *
     * Les inscriptions non affiliées sont écartées comme ailleurs
     * ({@see TrainingPack::committedCount()}) : une affiliation annulée gardait
     * son nom dans la liste d'un pack qu'elle ne suit plus.
     *
     * @return array{enrolled: list<array<string, mixed>>, pending: list<array<string, mixed>>, waiting: list<array<string, mixed>>, past: list<array<string, mixed>>}
     */
    #[Computed]
    public function packRoster(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['enrolled' => [], 'pending' => [], 'waiting' => [], 'past' => []];
        }

        $attendance = $this->packAttendance;
        $prorata = new TrainingPackProrata;

        // What each stage line still asks, read in one query: a stage is paid on
        // its own line, and the committee needs to see who has settled.
        $campBalances = $pack->is_camp
            ? Payment::query()
                ->where('payable_type', SubscriptionTrainingPack::class)
                ->whereIn('payable_id', DB::table('subscription_training_pack')->where('training_pack_id', $pack->id)->select('id'))
                ->where('status', 'pending')
                ->where(fn ($q) => $q->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
                ->get()
                ->groupBy('payable_id')
                ->map(fn ($claims): float => round((float) $claims->sum(fn (Payment $claim): float => $claim->balance()), 2))
            : collect();

        $rows = $pack->subscriptions()
            ->withPivot([
                'id',
                'invoiced_separately',
                'status',
                'waitlist_position',
                'confirmation_deadline',
                'starts_on',
                'ends_on',
                'override_amount',
                'override_reason',
            ])
            ->affiliated()
            ->with('user')
            ->get()
            ->map(function (Subscription $subscription) use ($attendance, $pack, $prorata, $campBalances): array {
                $user = $subscription->user;
                $pivot = $subscription->pivot;

                return [
                    'id' => $user->id,
                    'name' => $user->last_name . ' ' . $user->first_name,
                    'ranking' => $user->ranking?->getLabel(),
                    'status' => $pivot->status,
                    'position' => $pivot->waitlist_position,
                    'deadline' => $pivot->confirmation_deadline,
                    // Datée du début du pack ou sans date, c'est la même
                    // inscription : « depuis le début » dans les deux cas.
                    'startsOn' => $prorata->coversWholePack($pack, $pivot->starts_on) ? null : $pivot->starts_on,
                    'endsOn' => $pivot->ends_on,
                    'overrideAmount' => $pivot->override_amount !== null ? (int) $pivot->override_amount / 100 : null,
                    'overrideReason' => $pivot->override_reason,
                    'unpaid' => $subscription->status === 'pending',
                    'isCampLine' => (bool) $pivot->invoiced_separately,
                    'campBalance' => (bool) $pivot->invoiced_separately ? (float) ($campBalances[$pivot->id] ?? 0.0) : null,
                    'rate' => $attendance['counted'] === 0
                        ? null
                        : (int) round((($attendance['present'][$user->id] ?? 0) / $attendance['counted']) * 100),
                ];
            });

        // Collection de base, pas celle d'Eloquent : `map()` sur des tableaux la
        // dégrade, et le typage ci-dessous est ce qui l'a révélé.
        $byName = fn (Illuminate\Support\Collection $group): array => LocaleSort::byKey($group, 'name')->all();

        $waiting = $rows->whereIn('status', ['waiting', 'offered'])
            ->sortBy([
                // Une offre en cours passe devant : elle a une échéance, la file non.
                fn (array $a, array $b): int => ($b['status'] === 'offered' ? 1 : 0) <=> ($a['status'] === 'offered' ? 1 : 0),
                fn (array $a, array $b): int => ($a['position'] ?? PHP_INT_MAX) <=> ($b['position'] ?? PHP_INT_MAX),
            ])
            ->values()
            ->all();

        return [
            'enrolled' => $byName($rows->where('status', 'enrolled')),
            'pending' => $byName($rows->where('status', 'pending')),
            'waiting' => $waiting,
            'past' => $byName($rows->whereIn('status', ['left', 'cancelled'])),
        ];
    }

    /** @return Collection<int, TrainingPack> */
    #[Computed]
    public function packs(): Collection
    {
        if (! $this->viewSeason) {
            return new Collection;
        }

        // Le tri porte sur la position du niveau, pas sur son libellé : la
        // délégation décide de l'ordre (du plus jeune au plus fort), et le
        // renommer ne doit pas réordonner l'écran. Jointure à gauche pour que
        // les packs sans niveau restent listés.
        return TrainingPack::with(['room', 'trainer', 'eventPost', 'level'])
            ->select('training_packs.*')
            ->leftJoin('training_levels', 'training_levels.id', '=', 'training_packs.training_level_id')
            ->where('training_packs.season_id', $this->viewSeason->id)
            ->when(! $this->showInactive, fn (Builder $q) => $q->where('training_packs.is_active', true))
            ->orderBy('training_packs.is_active', 'desc')
            ->orderBy('training_levels.position')
            ->orderBy('training_packs.name')
            ->get();
    }

    /**
     * Les chiffres de tête de la fiche d'un pack.
     *
     * Les séances se comptent sur la collection déjà chargée pour l'onglet
     * « Séances » : les recompter en base ferait trois requêtes pour un total
     * que l'écran tient déjà en mémoire.
     *
     * @return array{enrolled: int, waiting: int, pending: int, max: int, capped: bool, spotsLeft: int|null, sessions: int, held: int, cancelled: int, upcoming: int, turnout: int|null}
     */
    #[Computed]
    public function packSummary(): array
    {
        $pack = $this->selectedPack;

        if (! $pack) {
            return ['enrolled' => 0, 'waiting' => 0, 'pending' => 0, 'max' => 0, 'capped' => false,
                'spotsLeft' => null, 'sessions' => 0, 'held' => 0, 'cancelled' => 0, 'upcoming' => 0, 'turnout' => null];
        }

        $roster = $this->packRoster;
        $attendance = $this->packAttendance;
        $sessions = $this->sessions;

        $cancelled = $sessions->filter(fn (Training $session): bool => $session->isCancelled())->count();
        $held = $sessions->filter(fn (Training $session): bool => ! $session->isCancelled() && $session->start->isPast())->count();

        $enrolled = count($roster['enrolled']);
        $max = $pack->effectiveMaxParticipants();
        // Un pack en libre-service n'a pas de places à compter : le plafond hérité
        // de la salle ne s'y applique pas (même règle que la carte de la liste).
        $capped = ! $pack->is_open_enrollment && $max > 0;

        $presentAmongEnrolled = array_sum(array_map(
            fn (array $row): int => $attendance['present'][$row['id']] ?? 0,
            $roster['enrolled'],
        ));

        return [
            'enrolled' => $enrolled,
            'waiting' => count($roster['waiting']),
            'pending' => count($roster['pending']),
            'max' => $max,
            'capped' => $capped,
            'spotsLeft' => $capped ? max(0, $max - $pack->committedCount()) : null,
            'sessions' => $sessions->count(),
            'held' => $held,
            'cancelled' => $cancelled,
            'upcoming' => $sessions->count() - $cancelled - $held,
            'turnout' => ($attendance['counted'] === 0 || $enrolled === 0)
                ? null
                : (int) round(($presentAmongEnrolled / ($attendance['counted'] * $enrolled)) * 100),
        ];
    }

    /** @return array<int, Carbon> */
    #[Computed]
    public function previewDates(): array
    {
        if (! $this->formStartTime) {
            return [];
        }

        $daysToGenerate = $this->formRecurrenceType === 'specific_days'
            ? array_map(intval(...), $this->formSpecificDays)
            : ($this->formDayOfWeek ? [$this->formDayOfWeek] : []);

        if ($daysToGenerate === []) {
            return [];
        }

        // Custom dates override season bounds
        $season = $this->wizardSeason;
        $startBound = $this->formPackStartDate
            ? Carbon::parse($this->formPackStartDate)->startOfDay()
            : $season?->start_at?->copy()->startOfDay();
        $endBound = $this->formPackEndDate
            ? Carbon::parse($this->formPackEndDate)->endOfDay()
            : $season?->end_at?->copy();

        if (! $startBound || ! $endBound) {
            return [];
        }

        $generator = app(TrainingDateGenerator::class);
        $allDates = [];

        foreach ($daysToGenerate as $dayOfWeek) {
            $firstDate = $startBound->copy();
            $diff = ($dayOfWeek - $firstDate->isoWeekday() + 7) % 7;
            $firstDate->addDays($diff);

            if ($firstDate->gt($endBound)) {
                continue;
            }

            try {
                $dates = $generator->generateDates(
                    $firstDate->toDateString(),
                    $endBound->toDateString(),
                    Recurrence::WEEKLY->name,
                );
                $allDates = array_merge($allDates, $dates);
            } catch (Exception) {
                continue;
            }
        }

        usort($allDates, fn (Carbon $a, Carbon $b): int => $a->timestamp <=> $b->timestamp);

        return array_values(array_filter(
            $allDates,
            fn (Carbon $d): bool => ! in_array($d->toDateString(), $this->formExcludedDates, true),
        ));
    }

    public function prevStep(): void
    {
        if ((int) $this->step > 1) {
            $this->step = (string) ((int) $this->step - 1);
        }
    }

    /**
     * Confie une séance à un autre coach que celui du pack.
     *
     * Le remplacement improvisé — titulaire malade le mardi soir, un collègue
     * prend la salle — laissait sinon la séance non pointée à vie : le
     * remplaçant n'y avait pas accès et le titulaire n'y était pas.
     *
     * Ne touche que cette séance : le coach du pack reste inchangé pour toutes
     * les autres.
     */
    public function reassignSessionCoach(int $trainingId, int $userId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $session = Training::findOrFail($trainingId);
        $coach = User::findOrFail($userId);

        $session->update(['trainer_id' => $coach->id]);

        unset($this->sessions);

        $this->success(__(':session handed over to :coach.', [
            'session' => $session->start->translatedFormat('D d/m'),
            'coach' => $coach->first_name . ' ' . $coach->last_name,
        ]), icon: 'o-arrow-path');
    }

    /** La modale partagée a sorti quelqu'un : la liste n'est plus la même. */
    #[On('training-pack-exited')]
    public function refreshAfterExit(): void
    {
        $this->forgetRoster();
    }

    public function refreshPacks(): void
    {
        unset($this->packs);
    }

    /**
     * What the confirmation modal reports before the committee commits.
     *
     * @return array{deleting: int, keeping: int, members: int}
     */
    #[Computed]
    public function regenerationImpact(): array
    {
        $pack = $this->packId ? TrainingPack::find($this->packId) : null;

        if (! $pack) {
            return ['deleting' => 0, 'keeping' => 0, 'members' => 0];
        }

        return [
            'deleting' => $pack->trainings()
                ->where('status', 'scheduled')
                ->where('start', '>=', Carbon::now())
                ->count(),
            'keeping' => $pack->trainings()
                ->where(fn (Builder $q) => $q->where('status', '!=', 'scheduled')->orWhere('start', '<', Carbon::now()))
                ->count(),
            'members' => $pack->enrolledCount(),
        ];
    }

    public function removeFilter(string $key): void
    {
        $this->reset([$key]);
    }

    public function restorePack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        TrainingPack::findOrFail($packId)->update(['is_active' => true]);
        unset($this->packs);
        $this->success(__('Pack back in the offer.'));
    }

    #[Computed]
    public function roomOptions(): array
    {
        return Room::orderBy('name')
            ->get()
            ->map(fn (Room $r): array => ['id' => $r->id, 'name' => $r->name])
            ->toArray();
    }

    public function save(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $rules = [
            'formSeasonId' => 'required|integer|min:1',
            'formName' => 'required|min:2|max:255',
            'formLevel' => 'required',
            'formType' => 'required',
            'formRoomId' => 'required|integer|min:1',
            'formStartTime' => 'required',
            'formDurationMinutes' => 'required|integer|min:15|max:480',
            'formMaxParticipants' => 'nullable|integer|min:1|max:999',
            'formPrice' => 'required|numeric|min:0',
            // The pack period is the pro rata's denominator: a pack that does
            // not declare it cannot be billed for the months actually held.
            'formPackStartDate' => 'required|date',
            'formPackEndDate' => 'required|date|after_or_equal:formPackStartDate',
        ];

        if ($this->formType !== '' && $this->formType !== TrainingType::FREE->value) {
            $rules['formTrainerId'] = 'required|integer|min:1';
        }

        if ($this->formRecurrenceType === 'weekly') {
            $rules['formDayOfWeek'] = 'required|integer|between:1,7';
        } else {
            $rules['formSpecificDays'] = 'required|array|min:1';
        }

        $this->validate($rules);

        // Editing the slot of a pack that already has sessions is destructive:
        // the old sessions have to go and be rebuilt. Say so before doing it.
        if ($this->packId && ! $this->regenerationConfirmed && $this->scheduleChanged()) {
            $this->regenerateModal = true;

            return;
        }

        $season = Season::findOrFail($this->formSeasonId);

        // Unlimited enrolment only makes sense for free practice: a directed or
        // supervised session with no cap leaves the coach discovering the
        // overbooking on the night, with no waiting list to have prevented it.
        $isOpenEnrollment = $this->formIsOpenEnrollment && $this->formType === TrainingType::FREE->value;

        // Build recurrence data
        if ($this->formRecurrenceType === 'specific_days') {
            $days = array_values(array_map(intval(...), $this->formSpecificDays));
            sort($days);
            $dayOfWeek = $days[0];
            $daysOfWeek = $days;
        } else {
            $dayOfWeek = $this->formDayOfWeek;
            $daysOfWeek = null;
        }

        $data = [
            'season_id' => $season->id,
            'name' => $this->formName,
            'training_level_id' => $this->formLevel ?: null,
            'type' => $this->formType,
            'trainer_id' => $this->formTrainerId ?: null,
            'room_id' => $this->formRoomId,
            'description' => $this->formDescription ?: null,
            'day_of_week' => $dayOfWeek,
            'days_of_week' => $daysOfWeek,
            'start_time' => $this->formStartTime,
            'duration_minutes' => $this->formDurationMinutes,
            'pack_start_date' => $this->formPackStartDate ?: null,
            'pack_end_date' => $this->formPackEndDate ?: null,
            'excluded_dates' => $this->formExcludedDates === [] ? null : array_values($this->formExcludedDates),
            'max_participants' => $isOpenEnrollment || $this->formMaxParticipants === ''
                ? null
                : (int) $this->formMaxParticipants,
            'is_open_enrollment' => $isOpenEnrollment,
            'enrollments_open' => $this->formEnrollmentsOpen,
            'price' => $this->formPrice,
            'allow_discount' => $this->formAllowDiscount,
        ];

        // Once a member is on it, a pack keeps its nature: switching it would
        // change at once what every enrolled member owes.
        $isCamp = $this->campNatureLocked ? (bool) $this->editedPack?->is_camp : $this->formIsCamp;

        $data['is_camp'] = $isCamp;
        $data['requires_approval'] = $isCamp && $this->formRequiresApproval;

        // A stage never takes nor triggers the automatic discounts: a member's
        // own price is forced on their line, with its reason.
        if ($isCamp) {
            $data['allow_discount'] = false;
        }

        // `is_active` n'appartient pas au formulaire : il est piloté par
        // « Retirer de l'offre » / « Remettre dans l'offre ». L'écrire ici
        // remettait en ligne, à chaque enregistrement, un pack qu'on venait de
        // retirer — y compris pour une simple correction de prix.
        $pack = $this->packId
            ? tap(TrainingPack::findOrFail($this->packId))->update($data)
            : TrainingPack::create($data + ['is_active' => true]);

        // Relever le plafond ouvre des places pour de bon : la file doit être
        // appelée, sinon les places se remplissent au premier arrivé pendant
        // que ceux qui attendaient gardent leur rang pour rien.
        app(TrainingWaitlistService::class)->releaseSpot($pack);

        if (! $this->packId) {
            $pack->generateSessions($season);

            $count = $pack->trainings()->count();
            $this->success(
                title: __('Pack created!'),
                description: __(':count sessions generated.', ['count' => $count]),
                icon: 'o-calendar',
            );
        } else {
            // Propagate trainer change to all linked sessions
            $pack->trainings()->update(['trainer_id' => $pack->trainer_id]);

            if ($this->regenerationConfirmed) {
                $this->rebuildFutureSessions($pack, $season);
            } else {
                $this->success(__('Pack updated!'), icon: 'o-check-circle');
            }
        }

        unset($this->packs);
        $this->wizardOpen = false;
        $this->resetWizardFields();
    }

    /**
     * Fixe le prix d'un membre sur le stage : la facture du stage suit, et ce
     * qui est déjà payé au-delà part en remboursement.
     */
    public function saveCampPrice(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $line = $this->campLineFor($this->campPriceUserId);

        if ($line === null) {
            return;
        }

        $amount = trim($this->campPriceAmount) === '' ? null : (float) str_replace(',', '.', $this->campPriceAmount);

        try {
            $refunded = (new AdjustTrainingCampLineAction)($line->subscription, $line->trainingPack, $amount, $this->campPriceReason);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->campPriceModal = false;
        $this->forgetRoster();

        $refunded > 0
            ? $this->warning(__('Price updated. :amount € to refund, sent to the treasury.', ['amount' => number_format($refunded, 2, ',', ' ')]))
            : $this->success(__('Price updated.'));
    }

    /**
     * Crée ou met à jour un niveau.
     *
     * Un niveau neuf se place en fin de liste : l'ordre est une décision de la
     * délégation, pas un effet de bord de la date de création.
     */
    public function saveLevel(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->validate([
            'levelForm.label' => 'required|string|min:2|max:60',
            'levelForm.color' => 'required|string|max:30',
        ]);

        $attributes = [
            'label' => $this->levelForm['label'],
            'color' => $this->levelForm['color'],
        ];

        if (! empty($this->levelForm['id'])) {
            TrainingLevel::findOrFail($this->levelForm['id'])->update($attributes);
        } else {
            TrainingLevel::create($attributes + [
                'position' => (int) TrainingLevel::max('position') + 1,
                'is_active' => true,
            ]);
        }

        $this->newLevel();

        unset($this->levelOptions, $this->levels);

        $this->success(__('Level saved.'), icon: 'o-check-circle');
    }

    /**
     * Has anything that decides *when and where* the sessions happen changed?
     *
     * Renaming the pack, editing its description or its price does not move a
     * single session, so it must not trigger a rebuild or an email.
     */
    public function scheduleChanged(): bool
    {
        $pack = $this->packId ? TrainingPack::find($this->packId) : null;

        if (! $pack) {
            return false;
        }

        $formDays = $this->formRecurrenceType === 'specific_days'
            ? array_values(array_map(intval(...), $this->formSpecificDays))
            : null;

        if ($formDays !== null) {
            sort($formDays);
        }

        $packDays = $pack->days_of_week ? array_map(intval(...), $pack->days_of_week) : null;

        if ($packDays !== null) {
            sort($packDays);
        }

        $formExcluded = array_values($this->formExcludedDates);
        $packExcluded = array_values($pack->excluded_dates ?? []);
        sort($formExcluded);
        sort($packExcluded);

        return $packDays !== $formDays
            || (int) $pack->day_of_week !== (int) ($formDays[0] ?? $this->formDayOfWeek)
            || substr((string) $pack->start_time, 0, 5) !== substr($this->formStartTime, 0, 5)
            || (int) $pack->duration_minutes !== $this->formDurationMinutes
            || (int) $pack->room_id !== $this->formRoomId
            || ($pack->pack_start_date?->toDateString() ?? '') !== $this->formPackStartDate
            || ($pack->pack_end_date?->toDateString() ?? '') !== $this->formPackEndDate
            || $packExcluded !== $formExcluded;
    }

    // ── Options ───────────────────────────────────────────────────────────────

    #[Computed]
    public function seasonOptions(): array
    {
        return Season::orderBy('start_at')
            ->get()
            ->map(fn (Season $s): array => [
                'id' => $s->id,
                'name' => $s->name . ($s->is_active ? ' (' . __('Active') . ')' : ''),
            ])
            ->toArray();
    }

    #[Computed]
    public function selectedPack(): ?TrainingPack
    {
        // `season` et `eventPost` sont lus par l'en-tête et les pastilles de la
        // fiche : sans eager loading, strict mode lève une LazyLoadingViolation.
        return $this->selectedPackId
            ? TrainingPack::with(['room', 'trainer', 'level', 'season', 'eventPost'])->find($this->selectedPackId)
            : null;
    }

    /** @return Collection<int, Training> */
    #[Computed]
    public function sessions(): Collection
    {
        return $this->selectedPackId
            ? Training::with(['room'])
                ->where('training_pack_id', $this->selectedPackId)
                ->orderBy('start')
                ->get()
            : new Collection;
    }

    /**
     * Ouvre ou ferme le libre-service sur un pack.
     *
     * Indépendant de « Retirer de l'offre » : un pack peut rester affiché sur
     * le site, complet, avec ses inscriptions closes. Les offres de liste
     * d'attente déjà envoyées ne sont pas rappelées — fermer empêche d'entrer,
     * pas d'avancer.
     */
    public function toggleEnrollments(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $pack = TrainingPack::findOrFail($packId);
        $pack->update(['enrollments_open' => ! $pack->enrollments_open]);

        unset($this->packs, $this->selectedPack);

        $pack->enrollments_open
            ? $this->success(__('Enrolments reopened for :pack.', ['pack' => $pack->name]), icon: 'o-lock-open')
            : $this->warning(__('Enrolments closed for :pack. The committee can still add members.', ['pack' => $pack->name]), icon: 'o-lock-closed');
    }

    public function toggleExcludeDate(string $date): void
    {
        if (in_array($date, $this->formExcludedDates, true)) {
            $this->formExcludedDates = array_values(
                array_filter($this->formExcludedDates, fn (string $d): bool => $d !== $date),
            );
        } else {
            $this->formExcludedDates[] = $date;
        }

        unset($this->previewDates);
    }

    /** Retire un niveau des listes sans toucher aux packs qui le portent. */
    public function toggleLevel(int $levelId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $level = TrainingLevel::findOrFail($levelId);
        $level->update(['is_active' => ! $level->is_active]);

        unset($this->levelOptions, $this->levels);
    }

    #[Computed]
    public function trainerOptions(): array
    {
        return User::role(Role::COACH->value)
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return collect(TrainingType::cases())
            ->map(fn (TrainingType $type): array => ['id' => $type->value, 'name' => $type->label()])
            ->toArray();
    }

    /**
     * Un stage se range dans la saison qui contient sa date de début : c'est
     * l'affiliation de cette saison qui couvrira le membre sur la table.
     */
    public function updatedFormPackStartDate(string $value): void
    {
        if (! $this->formIsCamp || $this->packId || $value === '') {
            return;
        }

        $season = Season::query()
            ->whereDate('start_at', '<=', $value)
            ->whereDate('end_at', '>=', $value)
            ->first();

        if ($season) {
            $this->formSeasonId = $season->id;
        }
    }

    // ── Session drill-down ────────────────────────────────────────────────────

    public function updatedShowAllSessions(): void
    {
        unset($this->attendanceMatrix);
    }

    #[Computed]
    public function viewSeason(): ?Season
    {
        return $this->viewSeasonId ? Season::find($this->viewSeasonId) : null;
    }

    public function with(): array
    {
        return [
            'activeSeason' => $this->activeSeason,
            'filterChips' => $this->filterChips,
            'discontinueImpact' => $this->discontinueImpact,
            'regenerationImpact' => $this->regenerationImpact,
            'viewSeason' => $this->viewSeason,
            'packs' => $this->packs,
            'selectedPack' => $this->selectedPack,
            'sessions' => $this->sessions,
            'previewDates' => $this->previewDates,
            'seasonOptions' => $this->seasonOptions,
            'levelOptions' => $this->levelOptions,
            'typeOptions' => $this->typeOptions,
            'trainerOptions' => $this->trainerOptions,
            'roomOptions' => $this->roomOptions,
            'dayOptions' => $this->dayOptions,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    /**
     * Take the pack off the offer without touching what is already running:
     * sessions go ahead, enrolled members keep their place and hear nothing.
     */
    public function withdrawPack(int $packId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        TrainingPack::findOrFail($packId)->update(['is_active' => false]);
        unset($this->packs);
        $this->withdrawPackModal = false;
        $this->withdrawingPackId = null;
        $this->warning(__('Pack withdrawn from the offer. Its sessions still run.'));
    }

    #[Computed]
    public function wizardSeason(): ?Season
    {
        return $this->formSeasonId ? Season::find($this->formSeasonId) : null;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Trainings'));
    }

    /**
     * Inscrit un membre à un stage depuis l'écran : directement, au-delà du
     * plafond s'il le faut, au prix du stage ou à un prix fixé avec son motif.
     */
    private function addMemberToCamp(TrainingPack $pack, Subscription $subscription): void
    {
        $price = trim($this->addMemberPrice) === '' ? null : (float) str_replace(',', '.', $this->addMemberPrice);

        try {
            (new EnrollInTrainingCampAction)($subscription, $pack, byClub: true, overrideAmount: $price, overrideReason: $this->addMemberPriceReason);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->warnWhenUnreachable($subscription->user);

        $this->addMemberModal = false;
        $this->addMemberUserId = 0;
        $this->addMemberPrice = '';
        $this->addMemberPriceReason = '';

        unset($this->packs, $this->selectedPack, $this->addMemberOptions, $this->addMemberOverCapacity, $this->attendanceMatrix, $this->packSummary, $this->packAttendance);
        $this->forgetRoster();

        $this->success(__(':member added to :pack.', [
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'pack' => $pack->name,
        ]), icon: 'o-user-plus');
    }

    /** La ligne de stage de ce membre, sur le stage consulté. */
    private function campLineFor(int $userId): ?SubscriptionTrainingPack
    {
        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($userId);

        if (! $pack?->is_camp || ! $subscription) {
            return null;
        }

        $line = (new TrainingCampBilling)->line($subscription, $pack);

        return $line?->invoiced_separately ? $line : null;
    }

    /** Le roster et tout ce qui en dérive, à relire après une écriture. */
    private function forgetRoster(): void
    {
        unset(
            $this->packs,
            $this->selectedPack,
            $this->addMemberOptions,
            $this->addMemberOverCapacity,
            $this->attendanceMatrix,
            $this->moveTargetOptions,
            $this->packRoster,
            $this->packSummary,
            $this->packAttendance,
        );
    }

    /**
     * Offer the season's own period as the pack's, which is what a season-long
     * pack covers. A camp overwrites both dates; nobody has to type the two
     * usual ones by hand.
     */
    private function prefillPackDatesFromSeason(): void
    {
        $season = $this->formSeasonId ? Season::find($this->formSeasonId) : null;

        $this->formPackStartDate = $season?->start_at?->toDateString() ?? '';
        $this->formPackEndDate = $season?->end_at?->toDateString() ?? '';
    }

    /**
     * Rebuild the sessions still to come, leaving history alone.
     *
     * Past sessions carry attendance — deleting them would rewrite every
     * member's presence rate. Cancelled ones were announced by email with their
     * own wording, and resurrecting them would contradict what members were told.
     */
    private function rebuildFutureSessions(TrainingPack $pack, Season $season): void
    {
        $deleted = $pack->trainings()
            ->where('status', 'scheduled')
            ->where('start', '>=', Carbon::now())
            ->delete();

        $pack->refresh();
        $pack->generateSessions($season);

        $created = $pack->trainings()
            ->where('status', 'scheduled')
            ->where('start', '>=', Carbon::now())
            ->count();

        $notified = 0;

        if ($this->notifyMembersOfChange) {
            $recipients = $pack->trainees()->where('emails_notifications', true)->get();
            $recipients->each->notify(new TrainingPackScheduleChangedNotification($pack));
            $notified = $recipients->count();
        }

        $this->success(
            title: __('Pack updated!'),
            description: __(':deleted session(s) replaced by :created, :notified member(s) notified.', [
                'deleted' => $deleted,
                'created' => $created,
                'notified' => $notified,
            ]),
            icon: 'o-calendar',
        );
    }

    private function resetWizardFields(): void
    {
        $this->packId = null;
        $this->step = '1';
        $this->formSeasonId = $this->activeSeason?->id ?? 0;
        $this->formName = '';
        $this->formLevel = 0;
        $this->formType = '';
        $this->formTrainerId = 0;
        $this->formRoomId = 0;
        $this->formDescription = '';
        $this->formRecurrenceType = 'weekly';
        $this->formDayOfWeek = null;
        $this->formSpecificDays = [];
        $this->formStartTime = '18:00';
        $this->formDurationMinutes = 90;
        $this->prefillPackDatesFromSeason();
        $this->formExcludedDates = [];
        $this->formPrice = 90;
        $this->formAllowDiscount = true;
        $this->formIsCamp = false;
        $this->formRequiresApproval = false;
        $this->formMaxParticipants = '';
        $this->formIsOpenEnrollment = false;
        $this->formEnrollmentsOpen = true;
        $this->regenerationConfirmed = false;
        $this->notifyMembersOfChange = true;
    }

    /** L'affiliation de ce membre pour la saison du pack consulté. */
    private function rosterSubscription(int $userId): ?Subscription
    {
        $pack = $this->selectedPack;

        if (! $pack || $userId === 0) {
            return null;
        }

        return Subscription::with(['user', 'season', 'trainingPacks', 'payments'])
            ->where('user_id', $userId)
            ->where('season_id', $pack->season_id)
            ->first();
    }

    /**
     * Ni l'annonce ni l'invitation au paiement ne sont parties : le comité est
     * le seul canal qui reste pour prévenir le membre.
     */
    private function warnWhenUnreachable(User $member): void
    {
        if ($member->contactEmails() !== []) {
            return;
        }

        $this->warning(__('No email address on file for :name — hand them the payment details.', [
            'name' => $member->first_name . ' ' . $member->last_name,
        ]));
    }
};
