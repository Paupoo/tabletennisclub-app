<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Services\TournamentService;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

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
                    'users AS active_registrations_count' => fn ($q) =>
                        $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
                ])
                ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $user->id)]),
        ])->get();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function delete(Room $room): void
    {
        $this->authorize('delete', $room);
        $room->delete();
        unset($this->rooms);
        $this->success(__('The room ' . $room->name . ' has been deleted.'));
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
        } catch (\LogicException $e) {
            $this->error($e->getMessage());
        }
    }

    public function cancelRegistration(int $tournamentId): void
    {
        /** @var User $user */
        $user = auth()->user();
        $tournament = Tournament::findOrFail($tournamentId);

        try {
            app(TournamentService::class)->cancelRegistration($tournament, $user);
            unset($this->rooms);
            $this->warning(__('Registration cancelled.'));
        } catch (\LogicException $e) {
            $this->error($e->getMessage());
        }
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        return [
            'rooms' => $this->rooms,
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Rooms'))
                ->toArray(),
        ];
    }
};
