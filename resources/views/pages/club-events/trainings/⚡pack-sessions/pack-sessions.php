<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledNotification;
use App\Domains\Trainings\Services\TrainingStaffing;
use App\Jobs\SendTrainingNoticeJob;
use App\Livewire\Concerns\ViewsTrainingPack;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * L'onglet Séances d'un pack : annuler, confier, renforcer une séance.
 */
new class extends Component
{
    use Toast;
    use ViewsTrainingPack;

    // ── Cancellation modal ────────────────────────────────────────────────────
    public bool $cancelModal = false;

    public string $cancelNote = '';

    public ?int $cancelTrainingId = null;

    public string $cancelType = 'FREE';

    public int $handOverCoachId = 0;

    public bool $handOverModal = false;

    public ?int $handOverSessionId = null;

    public ?int $sessionSparringsId = null;

    public bool $sessionSparringsModal = false;

    public int $sessionSparringUserId = 0;

    public function addSessionSparring(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if (! $this->sessionSparringsId || ! $this->sessionSparringUserId) {
            return;
        }

        app(TrainingStaffing::class)->addToSession(
            Training::findOrFail($this->sessionSparringsId),
            User::findOrFail($this->sessionSparringUserId),
            Auth::user(),
        );

        $this->sessionSparringUserId = 0;

        unset($this->sessions, $this->sessionSparrings);
    }

    /**
     * Tout membre peut recevoir une séance ou devenir sparring quand c'est le
     * comité qui le décide : c'est ce geste qui fait entrer quelqu'un dans
     * l'encadrement.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function anyMemberOptions(): array
    {
        return User::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('users.id')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->last_name . ' ' . $user->first_name])
            ->all();
    }

    public function confirmCancel(): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $training = Training::with('trainingPack')->findOrFail($this->cancelTrainingId);

        $type = $this->cancelType === 'CLOSED'
            ? TrainingCancellationType::CLOSED
            : TrainingCancellationType::FREE;

        $training->cancel($type, $this->cancelNote ?: null);

        if ($training->trainingPack) {
            SendTrainingNoticeJob::toEach(
                $training->trainingPack->trainees()->where('emails_notifications', true)->get(),
                new TrainingSessionCancelledNotification($training, $type, $this->cancelNote ?: null),
            );
        }

        app(TrainingStaffing::class)->notifyCancellation($training, auth()->user());

        unset($this->sessions);
        $this->dispatch('training-pack-changed');
        $this->cancelModal = false;
        $this->warning(__('Session cancelled. Members have been notified.'), icon: 'o-x-circle');
    }

    // ── Hand-over ─────────────────────────────────────────────────────────────

    public function confirmHandOver(): void
    {
        if (! $this->handOverSessionId || ! $this->handOverCoachId) {
            return;
        }

        $this->reassignSessionCoach($this->handOverSessionId, $this->handOverCoachId);

        $this->handOverModal = false;
        $this->handOverSessionId = null;
        $this->handOverCoachId = 0;
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

    public function openHandOver(int $trainingId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->handOverSessionId = $trainingId;
        $this->handOverCoachId = 0;
        $this->handOverModal = true;
    }

    public function openSessionSparrings(int $trainingId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        $this->sessionSparringsId = $trainingId;
        $this->sessionSparringUserId = 0;
        $this->sessionSparringsModal = true;

        unset($this->sessionSparrings);
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

        app(TrainingStaffing::class)->handOverSession($session, $coach, Auth::user());

        unset($this->sessions);

        $this->success(__(':session handed over to :coach.', [
            'session' => $session->start->translatedFormat('D d/m'),
            'coach' => $coach->first_name . ' ' . $coach->last_name,
        ]), icon: 'o-arrow-path');
    }

    // ── Sparrings ─────────────────────────────────────────────────────────────

    /** La fiche a changé les sparrings attitrés : les séances les portent. */
    #[On('training-pack-refresh')]
    public function refreshSessions(): void
    {
        unset($this->sessions);
    }

    public function removeSessionSparring(int $userId): void
    {
        Gate::authorize(Permission::TrainingsManage->value);

        if (! $this->sessionSparringsId) {
            return;
        }

        app(TrainingStaffing::class)->removeFromSession(
            Training::findOrFail($this->sessionSparringsId),
            User::findOrFail($userId),
            Auth::user(),
        );

        unset($this->sessions, $this->sessionSparrings);
    }

    /** @return Collection<int, Training> */
    #[Computed]
    public function sessions(): Collection
    {
        return Training::with(['room', 'trainer', 'sparrings'])
            ->where('training_pack_id', $this->packId)
            ->orderBy('start')
            ->get();
    }

    /**
     * Les sparrings attendus à la séance ouverte dans la modale.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function sessionSparrings(): Collection
    {
        $session = $this->sessionSparringsId ? Training::find($this->sessionSparringsId) : null;

        return $session?->sparrings()->orderBy('last_name')->orderBy('first_name')->get() ?? new Collection;
    }
};
