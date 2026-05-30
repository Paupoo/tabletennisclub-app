<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\MyMatches;

use App\Domains\Shared\Enums\InterclubAvailability;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast, HasBreadcrumbs;

    public ?int $editingInterclubId = null;

    public string $availabilityNote = '';

    public bool $bulkUnavailableModal = false;

    public function bulkMarkUnavailable(): void
    {
        $this->bulkUnavailableModal = false;
        $this->bulkMarkAvailability('unavailable');
    }

    public function bulkMarkAvailability(string $availability): void
    {
        $user = Auth::user();
        $enum = InterclubAvailability::from($availability);
        $teamIds = $user->teams()->pluck('teams.id');

        Interclub::where('start_date_time', '>=', now())
            ->where(fn ($q) => $q->whereIn('visited_team_id', $teamIds)
                ->orWhereIn('visiting_team_id', $teamIds))
            ->get()
            ->each(fn ($ic) => $ic->markAvailability($user, $enum));

        $this->success(__('Availability set for all upcoming matches.'), position: 'toast-bottom toast-end');
    }

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


    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__("My Matches"));
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
            'visitedTeam.league',
            'visitingTeam.club',
            'visitingTeam.league',
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

                $ourTeam = $interclub->ourTeam();
                $isHome = $interclub->isHome();
                $opponent = $interclub->opponentTeam()?->fullName() ?? '—';

                $division = $interclub->league?->division ?? '';
                $teamLabel = ($ourTeam?->name ?? '—') . ($division ? ' — ' . $division : '');

                $categoryRaw = $ourTeam?->league?->category;
                $categoryName = is_string($categoryRaw) ? $categoryRaw : ($categoryRaw?->value ?? 'MEN');
                [$categoryLabel, $categorySort] = match ($categoryName) {
                    'MEN'      => ['Hommes', 1],
                    'VETERANS' => ['Vétérans', 2],
                    'WOMEN'    => ['Dames', 3],
                    default    => ['—', 99],
                };

                return [
                    'id'                    => $interclub->id,
                    'category_label'        => $categoryLabel,
                    'category_sort'         => $categorySort,
                    'team_name'             => $teamLabel,
                    'opponent'              => $opponent,
                    'is_home'               => $isHome,
                    'division'              => $division,
                    'date'                  => $interclub->start_date_time->format('d/m/Y'),
                    'time'                  => $interclub->start_date_time->format('H:i'),
                    'address'               => $interclub->address ?? '—',
                    'week_number'           => $interclub->week_number,
                    'availability'          => $availability,
                    'availability_note'     => $pivot?->availability_note,
                    'is_selected'           => (bool) $pivot?->is_selected,
                    'selection_confirmed_at' => $pivot?->selection_confirmed_at,
                    'days_until'            => (int) now()->diffInDays($interclub->start_date_time, false),
                ];
            });

        $grouped = $interclubs
            ->sortBy([['category_sort', 'asc'], ['team_name', 'asc']])
            ->groupBy('category_label')
            ->map(fn ($catGroup) => $catGroup->groupBy('team_name'));

        $season = Season::current();
        $matchDayMap = $season ? Interclub::matchDayMap($season->id) : [];

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add(__('Interclubs'), route('admin.interclubs.captain-selection'))
                ->current(__('My Matches'))
                ->toArray(),
            'grouped' => $grouped,
            'availabilityOptions' => InterclubAvailability::cases(),
            'matchDayMap' => $matchDayMap,
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
