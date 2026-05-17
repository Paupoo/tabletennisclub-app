<?php

declare(strict_types=1);

use App\Enums\Recurrence;
use App\Enums\TrainingCancellationType;
use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\Training;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Notifications\Training\TrainingSessionCancelledNotification;
use App\Services\TrainingDateGenerator;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // ── Wizard state ──────────────────────────────────────────────────────────
    public bool $wizardOpen = false;

    public string $step = '1';

    public ?int $packId = null;

    // Step 1 — Pack info
    public int $formSeasonId = 0;

    public string $formName = '';

    public string $formLevel = '';

    public string $formType = '';

    public int $formTrainerId = 0;

    public int $formRoomId = 0;

    public string $formDescription = '';

    // Step 2 — Planning
    public string $formRecurrenceType = 'weekly'; // 'weekly' | 'specific_days'

    public ?int $formDayOfWeek = null;

    /** @var array<int, int|string> */
    public array $formSpecificDays = [];

    public string $formStartTime = '18:00';

    public int $formDurationMinutes = 90;

    public string $formPackStartDate = '';

    public string $formPackEndDate = '';

    /** @var array<int, string> */
    public array $formExcludedDates = [];

    // Step 3 — Price (in euros)
    public float $formPrice = 90;

    // ── View filter ───────────────────────────────────────────────────────────
    public int $viewSeasonId = 0;

    // ── Session drill-down ────────────────────────────────────────────────────
    public ?int $selectedPackId = null;

    // ── Cancellation modal ────────────────────────────────────────────────────
    public bool $cancelModal = false;

    public ?int $cancelTrainingId = null;

    public string $cancelType = 'FREE';

    public string $cancelNote = '';

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->viewSeasonId = Season::where('is_active', true)->value('id') ?? 0;
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function activeSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    #[Computed]
    public function viewSeason(): ?Season
    {
        return $this->viewSeasonId ? Season::find($this->viewSeasonId) : null;
    }

    /** @return Collection<int, TrainingPack> */
    #[Computed]
    public function packs(): Collection
    {
        if (! $this->viewSeason) {
            return new Collection();
        }

        return TrainingPack::with(['room', 'trainer'])
            ->where('season_id', $this->viewSeason->id)
            ->where('is_active', true)
            ->orderBy('level')
            ->orderBy('name')
            ->get();
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
            : new Collection();
    }

    #[Computed]
    public function wizardSeason(): ?Season
    {
        return $this->formSeasonId ? Season::find($this->formSeasonId) : null;
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

        if (empty($daysToGenerate)) {
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
            } catch (\Exception) {
                continue;
            }
        }

        usort($allDates, fn (Carbon $a, Carbon $b): int => $a->timestamp <=> $b->timestamp);

        return array_values(array_filter(
            $allDates,
            fn (Carbon $d): bool => ! in_array($d->toDateString(), $this->formExcludedDates, true),
        ));
    }

    // ── Options ───────────────────────────────────────────────────────────────

    #[Computed]
    public function seasonOptions(): array
    {
        return Season::orderBy('start_at', 'desc')
            ->get()
            ->map(fn (Season $s): array => [
                'id' => $s->id,
                'name' => $s->name.($s->is_active ? ' ('.__('Active').')' : ''),
            ])
            ->toArray();
    }

    #[Computed]
    public function levelOptions(): array
    {
        return collect(TrainingLevel::cases())
            ->map(fn ($e) => ['id' => $e->value, 'name' => $e->value])
            ->toArray();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return collect(TrainingType::cases())
            ->map(fn ($e) => ['id' => $e->value, 'name' => $e->value])
            ->toArray();
    }

    #[Computed]
    public function trainerOptions(): array
    {
        return User::where('is_active', true)
            ->where(fn ($q) => $q->where('is_coach', true)->orWhere('is_admin', true))
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    #[Computed]
    public function roomOptions(): array
    {
        return Room::orderBy('name')
            ->get()
            ->map(fn (Room $r) => ['id' => $r->id, 'name' => $r->name])
            ->toArray();
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

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->resetWizardFields();
        $this->wizardOpen = true;
        $this->step = '1';
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
        $this->formRecurrenceType = ! empty($pack->days_of_week) ? 'specific_days' : 'weekly';
        $this->formStartTime = $pack->start_time ?? '18:00';
        $this->formDurationMinutes = $pack->duration_minutes ?? 90;
        $this->formPackStartDate = $pack->pack_start_date?->toDateString() ?? '';
        $this->formPackEndDate = $pack->pack_end_date?->toDateString() ?? '';
        $this->formExcludedDates = $pack->excluded_dates ?? [];
        $this->formPrice = round($pack->price / 100, 2);

        $this->wizardOpen = true;
        $this->step = '1';
    }

    public function closeWizard(): void
    {
        $this->wizardOpen = false;
        $this->resetWizardFields();
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
        $this->formPackStartDate = '';
        $this->formPackEndDate = '';
        $this->formExcludedDates = [];
        $this->formPrice = 90;
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

    public function prevStep(): void
    {
        if ((int) $this->step > 1) {
            $this->step = (string) ((int) $this->step - 1);
        }
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
            'formPrice' => 'required|numeric|min:0',
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

        $season = Season::findOrFail($this->formSeasonId);

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
            'excluded_dates' => ! empty($this->formExcludedDates) ? array_values($this->formExcludedDates) : null,
            'is_active' => true,
            'price' => (int) ($this->formPrice * 100),
        ];

        $pack = $this->packId
            ? tap(TrainingPack::findOrFail($this->packId))->update($data)
            : TrainingPack::create($data);

        // Generate sessions only on create
        if (! $this->packId) {
            $pack->generateSessions($season);

            $count = $pack->trainings()->count();
            $this->success(
                title: __('Pack created!'),
                description: __(':count sessions generated.', ['count' => $count]),
                icon: 'o-calendar',
            );
        } else {
            $this->success(__('Pack updated!'), icon: 'o-check-circle');
        }

        unset($this->packs);
        $this->wizardOpen = false;
        $this->resetWizardFields();
    }

    public function deactivatePack(int $packId): void
    {
        TrainingPack::findOrFail($packId)->update(['is_active' => false]);
        unset($this->packs);
        $this->warning(__('Pack deactivated.'));
    }

    // ── Session drill-down ────────────────────────────────────────────────────

    public function viewSessions(int $packId): void
    {
        $this->selectedPackId = $packId;
        unset($this->selectedPack, $this->sessions);
    }

    public function backToList(): void
    {
        $this->selectedPackId = null;
    }

    // ── Cancellation ──────────────────────────────────────────────────────────

    public function openCancel(int $trainingId): void
    {
        $this->cancelTrainingId = $trainingId;
        $this->cancelType = 'FREE';
        $this->cancelNote = '';
        $this->cancelModal = true;
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

    // ── Render ────────────────────────────────────────────────────────────────

    public function with(): array
    {
        return [
            'activeSeason' => $this->activeSeason,
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
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->trainings()
                ->current(__('Trainings'))
                ->toArray(),
        ];
    }
};
