<?php

declare(strict_types=1);

use App\Enums\TrainingCancellationType;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\Training;
use App\Models\ClubEvents\Training\TrainingPack;
use App\Notifications\Training\TrainingSessionCancelledNotification;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // ── Session drill-down ────────────────────────────────────────────────────
    public ?int $selectedSessionId = null;

    // ── Attendance ────────────────────────────────────────────────────────────
    /** @var array<int, string> pivot status keyed by user_id */
    public array $attendanceStatus = [];

    // ── Cancellation modal ────────────────────────────────────────────────────
    public bool $cancelModal = false;

    public string $cancelType = 'FREE';

    public string $cancelNote = '';

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function activeSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    /** @return Collection<int, Training> */
    #[Computed]
    public function upcomingSessions(): Collection
    {
        /** @var User $coach */
        $coach = auth()->user();

        return Training::with(['trainingPack', 'room'])
            ->where('trainer_id', $coach->id)
            ->where('start', '>=', Carbon::now())
            ->where('status', 'scheduled')
            ->orderBy('start')
            ->get();
    }

    #[Computed]
    public function selectedSession(): ?Training
    {
        return $this->selectedSessionId
            ? Training::with(['trainingPack.room', 'room'])->find($this->selectedSessionId)
            : null;
    }

    /**
     * Enrolled members for the selected session's pack, with their stats.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function enrolledMembers(): Collection
    {
        if (! $this->selectedSession?->trainingPack) {
            return new Collection();
        }

        return $this->selectedSession->trainingPack->trainees()
            ->with(['guardians', 'teams.league'])
            ->get();
    }

    /** Presence rate for a user in the selected pack (attended / past sessions). */
    public function presenceRate(int $userId): int
    {
        if (! $this->selectedSession?->training_pack_id) {
            return 0;
        }

        $past = Training::where('training_pack_id', $this->selectedSession->training_pack_id)
            ->where('start', '<', Carbon::now())
            ->count();

        if ($past === 0) {
            return 0;
        }

        $present = Training::where('training_pack_id', $this->selectedSession->training_pack_id)
            ->where('start', '<', Carbon::now())
            ->whereHas('trainees', fn ($q) => $q->where('user_id', $userId)->where('training_user.status', 'present'))
            ->count();

        return (int) round(($present / $past) * 100);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function viewSession(int $trainingId): void
    {
        $this->selectedSessionId = $trainingId;
        $this->attendanceStatus = [];

        // Pre-load existing attendance status from pivot
        $session = Training::with(['trainees'])->findOrFail($trainingId);
        foreach ($session->trainees as $trainee) {
            $this->attendanceStatus[$trainee->id] = $trainee->pivot->status;
        }

        unset($this->selectedSession, $this->enrolledMembers);
    }

    public function backToList(): void
    {
        $this->selectedSessionId = null;
        unset($this->upcomingSessions);
    }

    public function setAttendance(int $userId, string $status): void
    {
        if (! $this->selectedSessionId) {
            return;
        }

        $this->attendanceStatus[$userId] = $status;

        $session = Training::findOrFail($this->selectedSessionId);

        if ($session->trainees()->where('user_id', $userId)->exists()) {
            $session->trainees()->updateExistingPivot($userId, ['status' => $status]);
        } else {
            $session->trainees()->attach($userId, ['status' => $status]);
        }
    }

    // ── Cancellation ──────────────────────────────────────────────────────────

    public function openCancel(): void
    {
        $this->cancelType = 'FREE';
        $this->cancelNote = '';
        $this->cancelModal = true;
    }

    public function confirmCancel(): void
    {
        $training = Training::with(['trainingPack'])->findOrFail($this->selectedSessionId);

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

        $this->cancelModal = false;
        $this->selectedSessionId = null;
        unset($this->upcomingSessions, $this->selectedSession);
        $this->warning(__('Session cancelled. Members have been notified.'), icon: 'o-x-circle');
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function with(): array
    {
        return [
            'activeSeason' => $this->activeSeason,
            'upcomingSessions' => $this->upcomingSessions,
            'selectedSession' => $this->selectedSession,
            'enrolledMembers' => $this->enrolledMembers,
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->trainings()
                ->current(__('My sessions'))
                ->toArray(),
        ];
    }
};
