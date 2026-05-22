<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\MyMatches;

use App\Enums\InterclubAvailability;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\Team;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public ?int $editingInterclubId = null;

    public string $availabilityNote = '';

    public function markAvailability(int $interclubId, string $availability): void
    {
        $user = Auth::user();
        $interclub = Interclub::findOrFail($interclubId);

        $team = $this->getUserTeamForInterclub($interclub, $user->id);

        if (! $team) {
            $this->error(__('You are not part of this team.'));

            return;
        }

        $enum = InterclubAvailability::from($availability);
        $interclub->markAvailability($user, $enum, $this->availabilityNote ?: null);

        $this->editingInterclubId = null;
        $this->availabilityNote = '';

        $this->success(__('Availability saved!'), position: 'toast-bottom toast-end');
    }

    public function openNote(int $interclubId): void
    {
        $this->editingInterclubId = $interclubId;
        $this->availabilityNote = '';
    }

    public function render(): View
    {
        return $this->view();
    }

    public function with(): array
    {
        $user = Auth::user();

        $teamIds = $user->teams()->pluck('teams.id');

        $interclubs = Interclub::with([
            'visitedTeam.club',
            'visitingTeam.club',
            'league',
        ])
            ->where(function ($q) use ($teamIds): void {
                $q->whereIn('visited_team_id', $teamIds)
                    ->orWhereIn('visiting_team_id', $teamIds);
            })
            ->where('start_date_time', '>=', now())
            ->orderBy('start_date_time')
            ->get()
            ->map(function (Interclub $interclub) use ($user) {
                $pivot = $interclub->users()
                    ->where('users.id', $user->id)
                    ->first()?->registration;

                $availability = $pivot?->availability
                    ? InterclubAvailability::from($pivot->availability)
                    : null;

                $ourClubLicence = config('app.club_licence');
                $ourTeam = $interclub->visitedTeam?->club?->licence === $ourClubLicence
                    ? $interclub->visitedTeam
                    : $interclub->visitingTeam;

                $isHome = $interclub->visitedTeam?->id === $ourTeam?->id;
                $opponentTeamObj = $isHome ? $interclub->visitingTeam : $interclub->visitedTeam;
                $opponent = trim(($opponentTeamObj?->club?->name ?? '') . ' ' . ($opponentTeamObj?->name ?? '')) ?: '—';

                $division = $interclub->league?->division ?? '';
                $teamLabel = ($ourTeam?->name ?? '—') . ($division ? ' — ' . $division : '');

                return [
                    'id' => $interclub->id,
                    'team_name' => $teamLabel,
                    'opponent' => $opponent,
                    'is_home' => $isHome,
                    'division' => $division,
                    'date' => $interclub->start_date_time->format('d/m/Y'),
                    'time' => $interclub->start_date_time->format('H:i'),
                    'address' => $interclub->address ?? '—',
                    'week_number' => $interclub->week_number,
                    'availability' => $availability,
                    'availability_note' => $pivot?->availability_note,
                    'is_selected' => (bool) $pivot?->is_selected,
                    'selection_confirmed_at' => $pivot?->selection_confirmed_at,
                    'days_until' => (int) now()->diffInDays($interclub->start_date_time, false),
                ];
            });

        $grouped = $interclubs->groupBy('team_name');

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('Interclubs'), route('admin.interclubs.captain-selection'))
                ->current(__('My Matches'))
                ->toArray(),
            'grouped' => $grouped,
            'availabilityOptions' => InterclubAvailability::cases(),
        ];
    }

    private function getUserTeamForInterclub(Interclub $interclub, int $userId): ?Team
    {
        $teamIds = Team::whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->pluck('id');

        if ($teamIds->contains($interclub->visited_team_id)) {
            return $interclub->visitedTeam;
        }

        if ($teamIds->contains($interclub->visiting_team_id)) {
            return $interclub->visitingTeam;
        }

        return null;
    }
};
