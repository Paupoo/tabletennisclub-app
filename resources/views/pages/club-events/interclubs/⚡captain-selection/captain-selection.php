<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\CaptainSelection;

use App\Enums\InterclubAvailability;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Services\InterclubAvailabilityService;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public string $captainMessage = '';

    public bool $drawerSelection = false;

    public bool $modalMessage = false;

    public ?int $selectedSeasonId = null;

    public ?int $selectedInterclubId = null;

    public ?int $selectedTeamId = null;

    public string $search = '';

    /** @var array<int, int> */
    public array $selectedPlayerIds = [];

    #[Locked]
    public ?int $currentUserId = null;

    public function boot(): void
    {
        $this->currentUserId = Auth::id();
    }

    public function mount(): void
    {
        $this->selectedSeasonId = Season::current()?->id;

        $user = Auth::user();
        if (! $user->is_admin && ! $user->is_committee_member) {
            $isCaptain = Team::where('captain_id', $user->id)->exists();
            abort_unless($isCaptain, 403);
        }
    }

    public function updatedSelectedSeasonId(): void
    {
        $this->selectedTeamId = null;
        $this->selectedInterclubId = null;
        $this->selectedPlayerIds = [];
    }

    public function confirmAndSend(InterclubAvailabilityService $service): void
    {
        $interclub = $this->selectedInterclub();

        if (! $interclub) {
            return;
        }

        $service->confirmSelection($interclub, $this->captainMessage);

        $this->modalMessage = false;
        $this->captainMessage = '';

        $this->success(
            __('Selection confirmed!'),
            __('Players have received their invitation.'),
            icon: 'o-paper-airplane'
        );
    }

    public function openSelection(int $interclubId): void
    {
        $this->selectedInterclubId = $interclubId;

        $interclub = Interclub::with(['visitedTeam.users', 'visitedTeam.club', 'visitingTeam.users', 'visitingTeam.club'])->findOrFail($interclubId);
        $team = $this->ourTeam($interclub);

        $this->selectedPlayerIds = $interclub->users()
            ->wherePivot('is_selected', true)
            ->pluck('users.id')
            ->toArray();

        $this->drawerSelection = true;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function requestAvailability(int $interclubId, InterclubAvailabilityService $service): void
    {
        $interclub = Interclub::findOrFail($interclubId);
        $service->requestAvailability($interclub);

        $this->success(
            __('Availability request sent!'),
            __('Team members who have not responded will receive an email.'),
            position: 'toast-bottom toast-end'
        );
    }

    public function saveSelection(): void
    {
        $interclub = $this->selectedInterclub();

        if (! $interclub) {
            return;
        }

        $existingIds = $interclub->users()->pluck('users.id')->toArray();
        $maxPlayers = $interclub->total_players;

        if (count($this->selectedPlayerIds) > $maxPlayers) {
            $this->warning(
                __('Too many players'),
                __('Maximum :n players allowed.', ['n' => $maxPlayers]),
                position: 'toast-bottom toast-end'
            );

            return;
        }

        foreach ($this->selectedPlayerIds as $userId) {
            if (in_array($userId, $existingIds)) {
                $interclub->users()->updateExistingPivot($userId, ['is_selected' => true]);
            } else {
                $interclub->users()->attach($userId, ['is_selected' => true]);
            }
        }

        $toDeselect = array_diff($existingIds, $this->selectedPlayerIds);
        foreach ($toDeselect as $userId) {
            $interclub->users()->updateExistingPivot($userId, ['is_selected' => false]);
        }

        $this->drawerSelection = false;
        $this->modalMessage = true;
    }

    public function togglePlayer(int $userId): void
    {
        $interclub = $this->selectedInterclub();
        $maxPlayers = $interclub?->total_players ?? 4;

        if (in_array($userId, $this->selectedPlayerIds)) {
            $this->selectedPlayerIds = array_values(array_diff($this->selectedPlayerIds, [$userId]));
        } else {
            if (count($this->selectedPlayerIds) >= $maxPlayers) {
                $this->warning(
                    __('Team full'),
                    __('Maximum :n players.', ['n' => $maxPlayers]),
                    position: 'toast-bottom toast-end'
                );

                return;
            }
            $this->selectedPlayerIds[] = $userId;
        }
    }

    public function with(): array
    {
        $user = Auth::user();
        $seasons = Season::orderBy('start_at')->get();
        $season = $this->selectedSeasonId
            ? $seasons->firstWhere('id', $this->selectedSeasonId)
            : Season::current();

        $teams = $this->loadAccessibleTeams($user, $season);

        $selectedTeam = $this->selectedTeamId
            ? $teams->firstWhere('id', $this->selectedTeamId)
            : $teams->first();

        $interclubs = [];
        $roster = [];
        $maxPlayers = 4;
        $drawerInterclub = null;

        if ($selectedTeam) {
            if (! $this->selectedTeamId) {
                $this->selectedTeamId = $selectedTeam->id;
            }

            $upcomingInterclubs = Interclub::with(['visitedTeam.users', 'visitedTeam.club', 'visitingTeam.users', 'visitingTeam.club'])
                ->where(function ($q) use ($selectedTeam): void {
                    $q->where('visited_team_id', $selectedTeam->id)
                        ->orWhere('visiting_team_id', $selectedTeam->id);
                })
                ->where('start_date_time', '>=', now())
                ->orderBy('start_date_time')
                ->get();

            $interclubs = $upcomingInterclubs->map(function (Interclub $ic) use ($selectedTeam) {
                $ourTeam = $this->ourTeam($ic);
                $opponent = $ic->visitedTeam?->id === $ourTeam?->id
                    ? ($ic->visitingTeam?->name ?? '—')
                    : ($ic->visitedTeam?->name ?? '—');

                $selectedCount = $ic->users()->wherePivot('is_selected', true)->count();
                $confirmedAt = $ic->users()
                    ->wherePivot('is_selected', true)
                    ->wherePivotNotNull('selection_confirmed_at')
                    ->count();

                $status = match (true) {
                    $ic->start_date_time < now() => 'past',
                    $confirmedAt > 0 => 'confirmed',
                    $selectedCount >= $ic->total_players => 'ready',
                    $selectedCount > 0 => 'pending',
                    default => 'future',
                };

                return [
                    'id' => $ic->id,
                    'wk' => $ic->week_number,
                    'date' => $ic->start_date_time->format('d/m/Y'),
                    'time' => $ic->start_date_time->format('H:i'),
                    'opp' => $opponent,
                    'status' => $status,
                    'selection_count' => $selectedCount,
                    'max_players' => $ic->total_players,
                ];
            });

            $maxPlayers = $selectedTeam->league?->category === 'MEN' ? 4 : 3;

            $roster = $selectedTeam->users->map(function (User $player) {
                return [
                    'id' => $player->id,
                    'name' => $player->last_name . ' ' . $player->first_name,
                    'rank' => $player->ranking ?? '—',
                    'available' => 'yes',
                ];
            });

            if ($this->selectedInterclubId) {
                $drawerInterclub = Interclub::find($this->selectedInterclubId);

                if ($drawerInterclub) {
                    $pivotMap = $drawerInterclub->users()
                        ->get()
                        ->keyBy('id')
                        ->map(fn ($u) => $u->registration);

                    $roster = $selectedTeam->users->map(function (User $player) use ($pivotMap) {
                        $pivot = $pivotMap->get($player->id);
                        $avail = $pivot?->availability
                            ? InterclubAvailability::from($pivot->availability)
                            : null;

                        return [
                            'id' => $player->id,
                            'name' => $player->last_name . ' ' . $player->first_name,
                            'rank' => $player->ranking ?? '—',
                            'availability' => $avail,
                            'availability_note' => $pivot?->availability_note,
                        ];
                    });
                }
            }
        }

        $searchResults = [];
        if (strlen($this->search) >= 2) {
            $searchResults = User::where('is_competitor', true)
                ->where(function ($q): void {
                    $q->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%');
                })
                ->whereNotIn('id', $this->selectedPlayerIds)
                ->limit(8)
                ->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->last_name . ' ' . $u->first_name,
                    'rank' => $u->ranking ?? '—',
                ]);
        }

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->current(__('Captain\'s Dashboard'))
                ->toArray(),
            'seasons_list' => $seasons->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'teams_list' => $teams->map(fn ($t) => [
                'id' => $t->id,
                'name' => ($t->club?->name ?? '?') . ' ' . $t->name,
            ]),
            'interclubs' => $interclubs,
            'roster' => $roster,
            'maxPlayers' => $maxPlayers,
            'searchResults' => $searchResults,
            'selectedTeam' => $selectedTeam,
            'drawerInterclub' => $drawerInterclub,
        ];
    }

    private function loadAccessibleTeams(User $user, ?Season $season): \Illuminate\Database\Eloquent\Collection
    {
        $query = Team::with(['league', 'club', 'captain', 'users'])
            ->inClub();

        if ($season) {
            $query->where('season_id', $season->id);
        }

        $query->where('captain_id', $user->id);

        return $query->get();
    }

    private function ourTeam(Interclub $interclub): ?Team
    {
        $ourClubLicence = config('app.club_licence');

        if ($interclub->visitedTeam?->club?->licence === $ourClubLicence) {
            return $interclub->visitedTeam;
        }

        if ($interclub->visitingTeam?->club?->licence === $ourClubLicence) {
            return $interclub->visitingTeam;
        }

        return $interclub->visitedTeam;
    }

    private function selectedInterclub(): ?Interclub
    {
        if (! $this->selectedInterclubId) {
            return null;
        }

        return Interclub::find($this->selectedInterclubId);
    }
};
