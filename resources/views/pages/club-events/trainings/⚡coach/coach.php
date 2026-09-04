<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Notifications\TrainingSessionCancelledNotification;
use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Domains\Trainings\Services\TrainingAttendanceService;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    // ── Attendance ────────────────────────────────────────────────────────────
    /** @var array<int, string> pivot status keyed by user_id */
    public array $attendanceStatus = [];

    // ── Cancellation modal ────────────────────────────────────────────────────
    public int $attendeeToAdd = 0;

    public bool $cancelModal = false;

    public string $cancelNote = '';

    public string $cancelType = 'FREE';

    // ── Session drill-down ────────────────────────────────────────────────────
    public ?int $selectedSessionId = null;

    // ── Computed ──────────────────────────────────────────────────────────────

    #[Computed]
    public function activeSeason(): ?Season
    {
        return Season::where('is_active', true)->first();
    }

    /**
     * Ajoute à la séance quelqu'un qui est venu sans y être inscrit.
     *
     * Rendre visible, rien de plus : aucune inscription n'est créée, rien n'est
     * facturé. Le comité décide ensuite, avec l'ajout manuel s'il le souhaite.
     */
    public function addAttendee(?int $userId = null): void
    {
        $userId ??= $this->attendeeToAdd;

        if (! $this->selectedSessionId || ! $userId) {
            return;
        }

        $session = Training::findOrFail($this->selectedSessionId);

        Gate::authorize('recordAttendance', $session);

        app(TrainingAttendanceService::class)->record($session, User::findOrFail($userId), 'present');

        $this->attendanceStatus[$userId] = 'present';
        $this->attendeeToAdd = 0;

        unset($this->walkIns, $this->attendeeOptions);
    }

    /**
     * Membres du club que le coach peut ajouter à la séance : tout le monde
     * sauf les inscrits du pack, déjà listés au-dessus.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function attendeeOptions(): array
    {
        if (! $this->selectedSession) {
            return [];
        }

        $enrolled = $this->enrolledMembers->pluck('id');

        return User::query()
            ->whereNotIn('id', $enrolled)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->last_name . ' ' . $user->first_name,
            ])
            ->toArray();
    }

    public function backToList(): void
    {
        $this->selectedSessionId = null;
        unset($this->upcomingSessions, $this->sessionsToRecord);
    }

    public function confirmCancel(): void
    {
        $training = Training::with(['trainingPack.level'])->findOrFail($this->selectedSessionId);

        Gate::authorize('recordAttendance', $training);

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
        unset($this->upcomingSessions, $this->sessionsToRecord, $this->selectedSession);
        $this->warning(__('Session cancelled. Members have been notified.'), icon: 'o-x-circle');
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
            return new Collection;
        }

        return $this->selectedSession->trainingPack->trainees()
            ->with(['guardians', 'teams.league'])
            ->get();
    }

    // ── Cancellation ──────────────────────────────────────────────────────────

    public function openCancel(): void
    {
        $this->cancelType = 'FREE';
        $this->cancelNote = '';
        $this->cancelModal = true;
    }

    /**
     * Taux de présence du membre sur le pack de la séance affichée.
     *
     * `null` tant qu'aucune séance n'a été pointée — la vue affiche « — »
     * plutôt qu'un 0 % qui accuserait à tort.
     */
    public function presenceRate(int $userId): ?int
    {
        $pack = $this->selectedSession?->trainingPack;

        return $pack ? app(TrainingAttendanceReport::class)->memberRate($pack, $userId) : null;
    }

    #[Computed]
    public function selectedSession(): ?Training
    {
        return $this->selectedSessionId
            ? Training::with(['trainingPack.room', 'room'])->find($this->selectedSessionId)
            : null;
    }

    /**
     * Ce qui reste à pointer : la séance en cours ou déjà passée, non annulée,
     * dont le pointage n'a jamais été validé.
     *
     * Elles remontent de la plus récente à la plus ancienne — celle du soir
     * d'abord, les oublis derrière.
     *
     * @return Collection<int, Training>
     */
    #[Computed]
    public function sessionsToRecord(): Collection
    {
        /** @var User $coach */
        $coach = auth()->user();

        return Training::with(['trainingPack.level', 'room'])
            ->where('trainer_id', $coach->id)
            ->where('start', '<', Carbon::now())
            ->where('status', 'scheduled')
            ->whereNull('attendance_taken_at')
            ->orderByDesc('start')
            ->get();
    }

    public function setAttendance(int $userId, string $status): void
    {
        if (! $this->selectedSessionId) {
            return;
        }

        $session = Training::findOrFail($this->selectedSessionId);

        Gate::authorize('recordAttendance', $session);

        $this->attendanceStatus[$userId] = $status;

        app(TrainingAttendanceService::class)->record($session, User::findOrFail($userId), $status);
    }

    /**
     * Les séances à venir. Le pointage y est possible mais rarement utile :
     * elles sont là pour que le coach voie son planning.
     *
     * @return Collection<int, Training>
     */
    #[Computed]
    public function upcomingSessions(): Collection
    {
        /** @var User $coach */
        $coach = auth()->user();

        return Training::with(['trainingPack.level', 'room'])
            ->where('trainer_id', $coach->id)
            ->where('start', '>=', Carbon::now())
            ->where('status', 'scheduled')
            ->orderBy('start')
            ->get();
    }

    /**
     * Clôt le pointage de la séance en cours de consultation.
     *
     * Les inscrits que le coach n'a pas touchés sont écrits `absent` : c'est ce
     * geste qui fait passer la séance de « non pointée » à « pointée », et qui
     * donne au taux de présence un dénominateur honnête.
     */
    public function validateAttendance(): void
    {
        if (! $this->selectedSessionId) {
            return;
        }

        $session = Training::with('trainingPack')->findOrFail($this->selectedSessionId);

        Gate::authorize('recordAttendance', $session);

        try {
            app(TrainingAttendanceService::class)->validate($session, auth()->user());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->selectedSessionId = null;

        unset($this->sessionsToRecord, $this->upcomingSessions, $this->selectedSession, $this->enrolledMembers);

        $this->success(__('Attendance recorded for this session.'), icon: 'o-check-circle');
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function viewSession(int $trainingId): void
    {
        $this->selectedSessionId = $trainingId;
        $this->attendanceStatus = [];

        // Pre-load existing attendance status from pivot
        $session = Training::with(['trainees'])->findOrFail($trainingId);

        Gate::authorize('recordAttendance', $session);

        foreach ($session->trainees as $trainee) {
            $this->attendanceStatus[$trainee->id] = $trainee->pivot->status;
        }

        unset($this->selectedSession, $this->enrolledMembers, $this->walkIns, $this->attendeeOptions);
    }

    /**
     * Les présents de la séance qui ne sont pas inscrits au pack.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function walkIns(): Collection
    {
        if (! $this->selectedSession) {
            return new Collection;
        }

        $enrolled = $this->enrolledMembers->pluck('id');

        return $this->selectedSession->trainees()
            ->whereNotIn('users.id', $enrolled)
            ->get();
    }

    public function with(): array
    {
        return [
            'activeSeason' => $this->activeSeason,
            'sessionsToRecord' => $this->sessionsToRecord,
            'upcomingSessions' => $this->upcomingSessions,
            'selectedSession' => $this->selectedSession,
            'enrolledMembers' => $this->enrolledMembers,
            'walkIns' => $this->walkIns,
            'attendeeOptions' => $this->attendeeOptions,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Trainings Coach'));
    }
};
