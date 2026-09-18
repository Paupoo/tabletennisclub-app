<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\AddMemberToTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\LeaveTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\MoveMemberBetweenTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
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
use App\Domains\Trainings\Services\TrainingDateGenerator;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs;
    use HasFilterDrawer;
    use Toast;

    // ── Ajout manuel d'un membre par le comité ────────────────────────────────
    public bool $addMemberModal = false;

    public string $addMemberStartsOn = '';

    public int $addMemberUserId = 0;

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

    public int $formRoomId = 0;

    // Step 1 — Pack info
    public int $formSeasonId = 0;

    /** @var array<int, int|string> */
    public array $formSpecificDays = [];

    public string $formStartTime = '18:00';

    public int $formTrainerId = 0;

    public string $formType = '';

    // ── Actions sur le roster ─────────────────────────────────────────────────
    public bool $leaveMemberModal = false;

    public string $leaveMemberName = '';

    public int $leaveMemberUserId = 0;

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

    // ── Computed ──────────────────────────────────────────────────────────────

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

        try {
            (new AddMemberToTrainingPackAction)(
                $subscription,
                $pack,
                $this->addMemberStartsOn ?: null,
                $subscription->has_other_family_members ? 2 : 1,
            );
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->addMemberModal = false;
        $this->addMemberUserId = 0;
        $this->addMemberStartsOn = '';

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

    /**
     * Retire une place validée et ouvre le remboursement qu'elle libère.
     *
     * Le montant remboursable n'est pas le prix du pack : quitter un pack peut
     * faire perdre la remise multi-packs, donc renchérir ceux qu'on garde.
     * {@see LeaveTrainingPackAction} calcule la vraie baisse du dû, plafonnée à
     * ce qui est effectivement rentré.
     */
    public function confirmLeaveMember(): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $pack = $this->selectedPack;
        $subscription = $this->rosterSubscription($this->leaveMemberUserId);

        if (! $pack || ! $subscription) {
            return;
        }

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();

        if ($pivot?->pivot->status !== 'enrolled') {
            $this->error(__('This pack is not enrolled and cannot be refunded this way.'));

            return;
        }

        $refundable = (new LeaveTrainingPackAction)(
            $subscription,
            $pack,
            $subscription->has_other_family_members ? 2 : 1,
            notifyUser: false,
        );

        $userName = $subscription->user->first_name . ' ' . $subscription->user->last_name;

        $this->leaveMemberModal = false;
        $this->leaveMemberUserId = 0;
        $this->forgetRoster();

        if ($refundable <= 0.0) {
            $this->success(__(':user removed from :pack. Nothing to refund — their balance is settled.', [
                'user' => $userName,
                'pack' => $pack->name,
            ]));

            return;
        }

        (new RequestSubscriptionRefundAction)($subscription, $refundable, __(':member has been removed from :pack after having paid.', [
            'member' => $userName,
            'pack' => $pack->name,
        ]));

        $iban = $subscription->user->iban;

        if ($iban) {
            $this->success(__(':user removed from :pack. Refund of :amount€ to be issued to :iban.', [
                'user' => $userName,
                'pack' => $pack->name,
                'amount' => number_format($refundable, 2),
                'iban' => $iban,
            ]));

            return;
        }

        $this->warning(__(':user removed from :pack. Refund of :amount€ required — no IBAN on file, please handle manually.', [
            'user' => $userName,
            'pack' => $pack->name,
            'amount' => number_format($refundable, 2),
        ]));
    }

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
        $this->regenerationConfirmed = true;
        $this->regenerateModal = false;

        $this->save();
    }

    public function confirmWithdrawPack(): void
    {
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

    public function editLevel(int $levelId): void
    {
        $level = TrainingLevel::findOrFail($levelId);

        $this->levelForm = [
            'id' => $level->id,
            'label' => $level->label,
            'color' => $level->color,
        ];
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

    public function mount(): void
    {
        $this->viewSeasonId = Season::where('is_active', true)->value('id') ?? 0;
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
            ->with('level')
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
        $this->addMemberUserId = 0;
        $this->addMemberStartsOn = '';
        $this->addMemberModal = true;

        unset($this->addMemberOptions, $this->addMemberOverCapacity);
    }

    // ── Cancellation ──────────────────────────────────────────────────────────

    public function openCancel(int $trainingId): void
    {
        $this->cancelTrainingId = $trainingId;
        $this->cancelType = 'FREE';
        $this->cancelNote = '';
        $this->cancelModal = true;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetWizardFields();
        $this->wizardOpen = true;
        $this->step = '1';
    }

    public function openDiscontinuePack(int $packId): void
    {
        $this->discontinuingPackId = $packId;
        $this->discontinueReason = '';
        $this->discontinuePackModal = true;
    }

    public function openEdit(int $packId): void
    {
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
        $this->formMaxParticipants = (string) ($pack->max_participants ?? '');
        $this->formIsOpenEnrollment = $pack->is_open_enrollment;
        $this->formEnrollmentsOpen = $pack->enrollments_open;

        $this->wizardOpen = true;
        $this->step = '1';
    }

    /** Ouvre la confirmation de sortie d'une place validée. */
    public function openLeaveMember(int $userId): void
    {
        Gate::authorize(Permission::SubscriptionsManage->value);

        $this->leaveMemberUserId = $userId;
        // Le nom est relu ici, jamais passé dans le wire:click : une apostrophe
        // dans « D'Hondt » clôturerait la chaîne de l'attribut.
        $this->leaveMemberName = (string) $this->rosterSubscription($userId)?->user->full_name;
        $this->leaveMemberModal = true;
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

    public function openWithdrawPack(int $packId): void
    {
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

        $rows = $pack->subscriptions()
            ->withPivot([
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
            ->map(function (Subscription $subscription) use ($attendance): array {
                $user = $subscription->user;
                $pivot = $subscription->pivot;

                return [
                    'id' => $user->id,
                    'name' => $user->last_name . ' ' . $user->first_name,
                    'ranking' => $user->ranking?->getLabel(),
                    'status' => $pivot->status,
                    'position' => $pivot->waitlist_position,
                    'deadline' => $pivot->confirmation_deadline,
                    'startsOn' => $pivot->starts_on,
                    'endsOn' => $pivot->ends_on,
                    'overrideAmount' => $pivot->override_amount !== null ? (int) $pivot->override_amount / 100 : null,
                    'overrideReason' => $pivot->override_reason,
                    'unpaid' => $subscription->status === 'pending',
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

    /**
     * Écarte une demande, ou retire quelqu'un de la file d'attente.
     *
     * Sans confirmation, et c'est voulu : {@see LeaveTrainingPackAction} détache
     * purement et simplement tout ce qui n'est pas `enrolled` — aucune date de
     * sortie, aucun euro, aucune trace. Une place validée, elle, passe par la
     * modale, parce qu'elle peut rendre de l'argent.
     */
    public function removeFromRoster(int $userId): void
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

        (new LeaveTrainingPackAction)(
            $subscription,
            $pack,
            $subscription->has_other_family_members ? 2 : 1,
        );

        $this->forgetRoster();

        $this->success(__(':member removed from :pack.', [
            'member' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
            'pack' => $pack->name,
        ]));
    }

    public function restorePack(int $packId): void
    {
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
};
