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
    public string $formName = '';

    public string $formLevel = '';

    public string $formType = '';

    public int $formTrainerId = 0;

    public int $formRoomId = 0;

    public string $formDescription = '';

    // Step 2 — Planning
    public ?int $formDayOfWeek = null;

    public string $formStartTime = '18:00';

    public int $formDurationMinutes = 90;

    // Step 3 — Price (in euros)
    public float $formPrice = 90;

    // ── Session drill-down ────────────────────────────────────────────────────
    public ?int $selectedPackId = null;

    // ── Cancellation modal ────────────────────────────────────────────────────
    public bool $cancelModal = false;

    public ?int $cancelTrainingId = null;

    public string $cancelType = 'FREE';

    public string $cancelNote = '';

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function activeSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    /** @return Collection<int, TrainingPack> */
    #[Computed]
    public function packs(): Collection
    {
        if (! $this->activeSeason) {
            return new Collection();
        }

        return TrainingPack::with(['room', 'trainer'])
            ->where('season_id', $this->activeSeason->id)
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
    public function previewDates(): array
    {
        if (! $this->formDayOfWeek || ! $this->formStartTime || ! $this->activeSeason) {
            return [];
        }

        $firstDate = $this->activeSeason->start_at->copy()->startOfDay();
        $diff = ($this->formDayOfWeek - $firstDate->isoWeekday() + 7) % 7;
        $firstDate->addDays($diff);

        if ($firstDate->gt($this->activeSeason->end_at)) {
            return [];
        }

        try {
            return app(TrainingDateGenerator::class)->generateDates(
                $firstDate->toDateString(),
                $this->activeSeason->end_at->toDateString(),
                Recurrence::WEEKLY->name,
            );
        } catch (\Exception) {
            return [];
        }
    }

    // ── Options ───────────────────────────────────────────────────────────────

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
        $this->formName = $pack->name;
        $this->formLevel = $pack->level->value;
        $this->formType = $pack->type->value;
        $this->formTrainerId = $pack->trainer_id ?? 0;
        $this->formRoomId = $pack->room_id;
        $this->formDescription = $pack->description ?? '';
        $this->formDayOfWeek = $pack->day_of_week;
        $this->formStartTime = $pack->start_time ?? '18:00';
        $this->formDurationMinutes = $pack->duration_minutes ?? 90;
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
        $this->formName = '';
        $this->formLevel = '';
        $this->formType = '';
        $this->formTrainerId = 0;
        $this->formRoomId = 0;
        $this->formDescription = '';
        $this->formDayOfWeek = null;
        $this->formStartTime = '18:00';
        $this->formDurationMinutes = 90;
        $this->formPrice = 90;
    }

    public function nextStep(): void
    {
        if ($this->step === '1') {
            $this->validate([
                'formName' => 'required|min:2|max:255',
                'formLevel' => 'required',
                'formType' => 'required',
                'formRoomId' => 'required|integer|min:1',
            ]);
        }

        if ($this->step === '2') {
            $this->validate([
                'formDayOfWeek' => 'required|integer|between:1,7',
                'formStartTime' => 'required',
                'formDurationMinutes' => 'required|integer|min:15|max:480',
            ]);
        }

        $this->step = (string) ((int) $this->step + 1);
    }

    public function prevStep(): void
    {
        if ((int) $this->step > 1) {
            $this->step = (string) ((int) $this->step - 1);
        }
    }

    public function save(): void
    {
        $this->validate([
            'formName' => 'required|min:2|max:255',
            'formLevel' => 'required',
            'formType' => 'required',
            'formRoomId' => 'required|integer|min:1',
            'formDayOfWeek' => 'required|integer|between:1,7',
            'formStartTime' => 'required',
            'formDurationMinutes' => 'required|integer|min:15|max:480',
            'formPrice' => 'required|numeric|min:0',
        ]);

        if (! $this->activeSeason) {
            $this->error(__('No active season found.'));

            return;
        }

        $data = [
            'season_id' => $this->activeSeason->id,
            'name' => $this->formName,
            'level' => $this->formLevel,
            'type' => $this->formType,
            'trainer_id' => $this->formTrainerId ?: null,
            'room_id' => $this->formRoomId,
            'description' => $this->formDescription ?: null,
            'day_of_week' => $this->formDayOfWeek,
            'start_time' => $this->formStartTime,
            'duration_minutes' => $this->formDurationMinutes,
            'is_active' => true,
        ];

        // Price stored in cents
        $data['price'] = (int) ($this->formPrice * 100);

        $pack = $this->packId
            ? tap(TrainingPack::findOrFail($this->packId))->update($data)
            : TrainingPack::create($data);

        // Generate sessions only on create
        if (! $this->packId) {
            $pack->generateSessions($this->activeSeason);

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
            'packs' => $this->packs,
            'selectedPack' => $this->selectedPack,
            'sessions' => $this->sessions,
            'previewDates' => $this->previewDates,
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
