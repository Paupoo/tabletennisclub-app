<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Services\TournamentService;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public User $user;

    public bool $onlyUpcoming = true;

    #[Computed]
    public function upcomingTournaments(): Collection
    {
        return Tournament::where('status', TournamentStatusEnum::PUBLISHED)
            ->where('start_date', '>=', now())
            ->withCount([
                'users AS active_registrations_count' => fn ($q) =>
                    $q->whereIn('tournament_user.registration_status', ['registered', 'confirmed', 'spot_offered']),
            ])
            ->with(['users' => fn ($q) => $q->where('tournament_user.user_id', $this->user->id)])
            ->orderBy('start_date')
            ->get();
    }

    #[Computed]
    public function myPastTournaments(): Collection
    {
        return $this->user->tournaments()
            ->where('start_date', '<', now())
            ->orderByDesc('start_date')
            ->limit(10)
            ->get();
    }

    public function register(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);
        app(TournamentService::class)->registerUser($tournament, $this->user);
        unset($this->upcomingTournaments);
        $this->success(__('Registration confirmed!'));
    }

    public function cancelRegistration(int $tournamentId): void
    {
        $tournament = Tournament::findOrFail($tournamentId);
        app(TournamentService::class)->cancelRegistration($tournament, $this->user);
        unset($this->upcomingTournaments);
        $this->warning(__('Registration cancelled.'));
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Events & Activities'))
                ->toArray(),
        ];
    }
};
