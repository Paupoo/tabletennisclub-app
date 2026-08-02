<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Recurrence;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackScheduleChangedNotification;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledNotification;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs;
    use HasFilterDrawer;
    use Toast;

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

    /** @var array<int, string> */
    public array $formExcludedDates = [];

    public bool $formIsOpenEnrollment = false;

    public string $formLevel = '';

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

    /** Ticked by default: forgetting to warn members is worse than one extra mail. */
    public bool $notifyMembersOfChange = true;

    public ?int $packId = null;

    public bool $regenerateModal = false;

    public bool $regenerationConfirmed = false;

    // ── Session drill-down ────────────────────────────────────────────────────
    public ?int $selectedPackId = null;

    /** Show packs withdrawn from the offer, so they can be found and put back. */
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

    public function backToList(): void
    {
        $this->selectedPackId = null;
    }

    public function clearFilters(): void
    {
        $this->viewSeasonId = Season::where('is_active', true)->value('id') ?? 0;
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

        unset($this->sessions);
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

        $activeSeasonId = Season::where('is_active', true)->value('id') ?? 0;

        if ($this->viewSeasonId !== $activeSeasonId) {
            $seasonName = Season::find($this->viewSeasonId)?->name ?? __('All seasons');
            $chips[] = ['key' => 'viewSeasonId', 'label' => __('Season') . ': ' . $seasonName];
        }

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
        return collect(TrainingLevel::cases())
            ->map(fn ($e): array => ['id' => $e->value, 'name' => $e->value])
            ->toArray();
    }

    public function mount(): void
    {
        $this->viewSeasonId = Season::where('is_active', true)->value('id') ?? 0;
    }

    public function nextStep(): void
    {
        if ($this->step === '1') {
            $rules = [
                'formSeasonId' => 'required|integer|min:1',
                'formName' => 'required|min:2|max:255',
                'formLevel' => 'required',
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
        $this->formLevel = $pack->level->value;
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

        $this->wizardOpen = true;
        $this->step = '1';
    }

    public function openWithdrawPack(int $packId): void
    {
        $this->withdrawingPackId = $packId;
        $this->withdrawPackModal = true;
    }

    /** @return Collection<int, TrainingPack> */
    #[Computed]
    public function packs(): Collection
    {
        if (! $this->viewSeason) {
            return new Collection;
        }

        return TrainingPack::with(['room', 'trainer', 'eventPost'])
            ->where('season_id', $this->viewSeason->id)
            ->when(! $this->showInactive, fn (Builder $q) => $q->where('is_active', true))
            ->orderBy('is_active', 'desc')
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, Carbon> */
    #[Computed]
    public function previewDates(): array
    {
        if (! $this->formStartTime) {
            return [];
        }

        $daysToGenerate = $this->formRecurrenceType === 'specific_days'
            ? array_map('intval', $this->formSpecificDays)
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
        if ($key === 'viewSeasonId') {
            $this->viewSeasonId = Season::where('is_active', true)->value('id') ?? 0;

            return;
        }

        $this->reset([$key]);
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
            $days = array_values(array_map('intval', $this->formSpecificDays));
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
            'level' => $this->formLevel,
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
            'is_active' => true,
            'price' => $this->formPrice,
            'allow_discount' => $this->formAllowDiscount,
        ];

        $pack = $this->packId
            ? tap(TrainingPack::findOrFail($this->packId))->update($data)
            : TrainingPack::create($data);

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
            ? array_values(array_map('intval', $this->formSpecificDays))
            : null;

        if ($formDays !== null) {
            sort($formDays);
        }

        $packDays = $pack->days_of_week ? array_map('intval', $pack->days_of_week) : null;

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
        return $this->selectedPackId
            ? TrainingPack::with(['room', 'trainer'])->find($this->selectedPackId)
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
            ->map(fn ($e): array => ['id' => $e->value, 'name' => $e->value])
            ->toArray();
    }

    #[Computed]
    public function viewSeason(): ?Season
    {
        return $this->viewSeasonId ? Season::find($this->viewSeasonId) : null;
    }

    // ── Session drill-down ────────────────────────────────────────────────────

    public function viewSessions(int $packId): void
    {
        $this->selectedPackId = $packId;
        unset($this->selectedPack, $this->sessions);
    }

    // ── Render ────────────────────────────────────────────────────────────────

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
        $this->formLevel = '';
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
        $this->regenerationConfirmed = false;
        $this->notifyMembersOfChange = true;
    }
};
