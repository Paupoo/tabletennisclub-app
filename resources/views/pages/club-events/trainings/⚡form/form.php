<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Recurrence;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\Enums\TrainingType;
use App\Domains\Trainings\Models\TrainingLevel;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackScheduleChangedNotification;
use App\Domains\Trainings\Services\TrainingDateGenerator;
use App\Domains\Trainings\Services\TrainingStaffing;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use App\Jobs\SendTrainingNoticeJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * L'assistant d'un pack, en page : créer, ou modifier ce qui décide de ses séances.
 */
new class extends Component
{
    use HasBreadcrumbs;
    use Toast;

    public bool $formAllowDiscount = true;

    public ?int $formDayOfWeek = null;

    public string $formDescription = '';

    public int $formDurationMinutes = 90;

    public bool $formEnrollmentsOpen = true;

    /** @var array<int, string> */
    public array $formExcludedDates = [];

    /** Euros, empty for the member price. A stage only. */
    public string $formExternalPrice = '';

    /** Y-m-d, empty for members only. A stage only. */
    public string $formExternalsOpenOn = '';

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

    /** Ticked by default: forgetting to warn members is worse than one extra mail. */
    public bool $notifyMembersOfChange = true;

    #[Locked]
    public ?int $packId = null;

    public bool $regenerateModal = false;

    public bool $regenerationConfirmed = false;

    public string $step = '1';

    #[Computed]
    public function activeSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
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
     * Confirmation step for a slot change on a pack that already has sessions.
     */
    public function confirmRegeneration(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->regenerationConfirmed = true;
        $this->regenerateModal = false;

        $this->save();
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

    #[Computed]
    public function editedPack(): ?TrainingPack
    {
        return $this->packId ? TrainingPack::find($this->packId) : null;
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

    /**
     * Création (`/trainings/create`) ou modification (`/trainings/{pack}/edit`).
     */
    public function mount(?TrainingPack $pack = null): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if ($pack?->exists) {
            $this->fillFromPack($pack);
        } else {
            $this->resetWizardFields();
        }
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
            'formExternalPrice' => 'nullable|integer|min:0',
            'formExternalsOpenOn' => 'nullable|date',
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

        // Only a stage takes non-members: the date is the opt-in, members keep
        // the priority until then.
        $data['external_price'] = $isCamp && $this->formExternalPrice !== '' ? (int) $this->formExternalPrice : null;
        $data['externals_open_on'] = $isCamp && $this->formExternalsOpenOn !== '' ? $this->formExternalsOpenOn : null;

        // A stage never takes nor triggers the automatic discounts: a member's
        // own price is forced on their line, with its reason.
        if ($isCamp) {
            $data['allow_discount'] = false;
        }

        // `is_active` n'appartient pas au formulaire : il est piloté par
        // « Retirer de l'offre » / « Remettre dans l'offre ». L'écrire ici
        // remettait en ligne, à chaque enregistrement, un pack qu'on venait de
        // retirer — y compris pour une simple correction de prix.
        $formerTrainerId = $this->packId ? TrainingPack::findOrFail($this->packId)->trainer_id : null;

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
            app(TrainingStaffing::class)->followPackCoach($pack, $formerTrainerId);

            if ($this->regenerationConfirmed) {
                $this->rebuildFutureSessions($pack, $season);
            } else {
                $this->success(__('Pack updated!'), icon: 'o-check-circle');
            }
        }

        $this->redirectRoute('admin.trainings.show', ['pack' => $pack], navigate: true);
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

    /** @return array<string, mixed> */
    public function with(): array
    {
        return [
            'previewDates' => $this->previewDates,
            'regenerationImpact' => $this->regenerationImpact,
            'seasonOptions' => $this->seasonOptions,
            'levelOptions' => $this->levelOptions,
            'typeOptions' => $this->typeOptions,
            'trainerOptions' => $this->trainerOptions,
            'roomOptions' => $this->roomOptions,
            'dayOptions' => $this->dayOptions,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    #[Computed]
    public function wizardSeason(): ?Season
    {
        return $this->formSeasonId ? Season::find($this->formSeasonId) : null;
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        $pack = $this->editedPack;

        return $pack
            ? Breadcrumb::make()->home()->trainings()->add($pack->name, route('admin.trainings.show', $pack))->current(__('Edit pack'))
            : Breadcrumb::make()->home()->trainings()->current(__('New training pack'));
    }

    private function fillFromPack(TrainingPack $pack): void
    {
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
        $this->formExternalPrice = $pack->external_price !== null ? (string) $pack->external_price : '';
        $this->formExternalsOpenOn = $pack->externals_open_on?->toDateString() ?? '';
        $this->formMaxParticipants = (string) ($pack->max_participants ?? '');
        $this->formIsOpenEnrollment = $pack->is_open_enrollment;
        $this->formEnrollmentsOpen = $pack->enrollments_open;
        $this->step = '1';
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
            SendTrainingNoticeJob::toEach($recipients, new TrainingPackScheduleChangedNotification($pack));
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
        $this->formExternalPrice = '';
        $this->formExternalsOpenOn = '';
        $this->formMaxParticipants = '';
        $this->formIsOpenEnrollment = false;
        $this->formEnrollmentsOpen = true;
        $this->regenerationConfirmed = false;
        $this->notifyMembersOfChange = true;
    }
};
