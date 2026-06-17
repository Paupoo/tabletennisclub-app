<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public bool $deleteRoomModal = false;

    public ?int $deletingRoomId = null;

    public function cancelRegistration(int $tournamentId): void
    {
        /** @var User $user */
        $user = auth()->user();
        $tournament = Tournament::findOrFail($tournamentId);

        try {
            app(TournamentService::class)->cancelRegistration($tournament, $user);
            unset($this->rooms);
            $this->warning(__('Registration cancelled.'));
        } catch (LogicException $e) {
            $this->error($e->getMessage());
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function confirmDeleteRoom(int $id): void
    {
        $this->deletingRoomId = $id;
        $this->deleteRoomModal = true;
    }

    public function delete(Room $room): void
    {
        $this->authorize('delete', $room);

        $hasRelatedRecords = $room->tables()->exists()
            || $room->trainings()->exists()
            || $room->trainingPacks()->exists()
            || $room->interclubs()->exists()
            || $room->tournaments()->exists();

        if ($hasRelatedRecords) {
            $this->error(__('This room cannot be deleted because it has linked tables, trainings, or events.'));

            return;
        }

        $room->delete();
        unset($this->rooms);
        $this->success(__('The room :name has been deleted.', ['name' => $room->name]));
    }

    public function deleteRoom(): void
    {
        if ($this->deletingRoomId) {
            $this->delete(Room::findOrFail($this->deletingRoomId));
        }
        $this->deleteRoomModal = false;
        $this->deletingRoomId = null;
    }

    public function register(int $tournamentId): void
    {
        /** @var User $user */
        $user = auth()->user();
        $tournament = Tournament::findOrFail($tournamentId);

        try {
            app(TournamentService::class)->registerUser($tournament, $user);
            unset($this->rooms);
            $this->success(__('Registration confirmed!'));
        } catch (LogicException $e) {
            $this->error($e->getMessage());
        }
    }

    public function render(): View
    {
        return $this->view();
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /** @return Collection<int, Room> */
    #[Computed]
    public function rooms(): Collection
    {
        $start = now();
        $end = (clone $start)->addWeeks(2);

        /** @var User $user */
        $user = auth()->user();

        return Room::with([
            'trainings' => fn ($query) => $query
                ->with('trainer')
                ->whereBetween('start', [$start, $end]),

            'interclubs' => fn ($query) => $query
                ->whereBetween('start_date_time', [$start, $end]),

            'tournaments' => fn ($query) => $query
                ->where('status', TournamentStatusEnum::PUBLISHED)
                ->whereBetween('start_date', [$start, $end])
                ->withCount([
                    'users AS active_registrations_count' => fn ($q) => $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
                ])
                ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $user->id)]),
        ])->get();
    }

    public function with(): array
    {
        return [
            'rooms' => $this->rooms,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ];
    }

    // ── Render ────────────────────────────────────────────────────────────────

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Rooms'));
    }
};
