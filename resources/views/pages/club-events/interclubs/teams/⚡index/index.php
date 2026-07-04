<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams;

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use App\Domains\Shared\Enums\TeamName;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast;

    public bool $createModal = false;

    public bool $deleteAllModal = false;

    public bool $deleteModal = false;

    public string $newCategory = '';

    public string $newDivision = '';

    public string $newLevel = '';

    public string $newTeamName = '';

    public string $search = '';

    public ?int $selectedSeasonId = null;

    public ?int $teamToDelete = null;

    public function clearFilters(): void
    {
        $this->selectedSeasonId = Season::current()?->id;
    }

    public function confirmDelete(int $id): void
    {
        abort_unless(Auth::user()->is_admin || Auth::user()->is_committee_member, 403);

        $this->teamToDelete = $id;
        $this->deleteModal = true;
    }

    public function createTeam(): void
    {
        abort_unless(Auth::user()->is_admin || Auth::user()->is_committee_member, 403);

        $this->validate([
            'newTeamName' => ['required', 'string'],
            'newCategory' => ['required', 'string'],
            'newLevel' => ['required', 'string'],
            'newDivision' => ['required', 'string'],
        ], [
            'newTeamName.required' => 'Choisissez une lettre pour l\'équipe.',
            'newCategory.required' => __('Please select a category.'),
            'newLevel.required' => __('Please select a level.'),
            'newDivision.required' => 'Indiquez la division.',
        ]);

        $season = Season::current();

        if (! $season) {
            $this->error('Aucune saison active.');

            return;
        }

        $ourClub = Club::own();

        $league = League::firstOrCreate([
            'category' => $this->newCategory,
            'level' => $this->newLevel,
            'division' => $this->newDivision,
            'season_id' => $season->id,
        ]);

        Team::create([
            'name' => $this->newTeamName,
            'season_id' => $season->id,
            'league_id' => $league->id,
            'club_id' => $ourClub?->id,
        ]);

        $this->reset('newTeamName', 'newCategory', 'newLevel', 'newDivision');
        $this->createModal = false;

        $this->success('Équipe créée.');
    }

    public function delete(): void
    {
        $team = Team::findOrFail($this->teamToDelete);
        $team->users()->detach();
        $team->delete();

        $this->teamToDelete = null;
        $this->deleteModal = false;

        $this->success('Équipe supprimée.');
    }

    public function deleteAll(): void
    {
        abort_unless(Auth::user()->is_admin || Auth::user()->is_committee_member, 403);

        $season = Season::current();

        if (! $season) {
            $this->error('Aucune saison active.');
            $this->deleteAllModal = false;

            return;
        }

        $teams = Team::inClub()->where('season_id', $season->id)->get();

        foreach ($teams as $team) {
            $team->users()->detach();
            $team->delete();
        }

        $this->deleteAllModal = false;
        $this->success("{$teams->count()} équipes supprimées.");
    }

    /** @return array<int, array{key: string, label: string}> */
    #[Computed]
    public function filterChips(): array
    {
        return $this->getFilterChips();
    }

    /** @return array<int, array{key: string, label: string}> */
    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->selectedSeasonId !== Season::current()?->id) {
            $seasonName = Season::find($this->selectedSeasonId)?->name ?? __('All seasons');
            $chips[] = ['key' => 'selectedSeasonId', 'label' => __('Season') . ': ' . $seasonName];
        }

        return $chips;
    }

    public function mount(): void
    {
        $this->selectedSeasonId = Season::current()?->id;
    }

    public function removeFilter(string $key): void
    {
        if ($key === 'selectedSeasonId') {
            $this->selectedSeasonId = Season::current()?->id;

            return;
        }

        $this->reset([$key]);
    }

    public function render(): View
    {
        return $this->view();
    }

    public function teams(): Collection
    {
        $season = $this->selectedSeasonId
            ? Season::find($this->selectedSeasonId)
            : Season::current();

        if (! $season) {
            return collect();
        }

        $teams = Team::inClub()
            ->where('season_id', $season->id)
            ->with(['league', 'captain', 'users', 'club'])
            ->get();

        $nextMatches = Interclub::where('season_id', $season->id)
            ->where('start_date_time', '>', now())
            ->whereIn('visited_team_id', $teams->pluck('id'))
            ->orWhere(fn ($q) => $q
                ->where('season_id', $season->id)
                ->where('start_date_time', '>', now())
                ->whereIn('visiting_team_id', $teams->pluck('id'))
            )
            ->orderBy('start_date_time')
            ->get();

        $categoryLabels = [
            'MEN' => 'Hommes',
            'VETERANS' => 'Vétérans',
            'WOMEN' => 'Dames',
        ];

        return $teams
            ->map(function (Team $team) use ($nextMatches, $categoryLabels) {
                $nextMatch = $nextMatches->first(fn (Interclub $ic) => $ic->visited_team_id === $team->id || $ic->visiting_team_id === $team->id
                );

                $rawCategory = $team->league?->category ?? '';
                $category = $categoryLabels[$rawCategory] ?? $rawCategory;

                $levelLabels = array_column(LeagueLevel::cases(), 'value', 'name');
                $levelLabel = $levelLabels[$team->league?->level] ?? $team->league?->level;
                $division = implode(' – ', array_filter([$levelLabel, $team->league?->division]));

                return (object) [
                    'id' => $team->id,
                    'name' => trim(($team->club?->name ?? '') . ' ' . $team->name),
                    'teamLetter' => $team->name,
                    'division' => $division ?: '—',
                    'category' => $category,
                    'captain_name' => $team->captain
                        ? $team->captain->first_name . ' ' . $team->captain->last_name
                        : '—',
                    'membersCount' => $team->users->count(),
                    'rank' => null,
                    'nextMatchDate' => $nextMatch?->start_date_time,
                ];
            })
            ->when($this->search, fn (Collection $c) => $c->filter(
                fn ($team) => str_contains(strtolower($team->name), strtolower($this->search))
                    || str_contains(strtolower($team->captain_name), strtolower($this->search))
            ));
    }

    public function with(): array
    {
        $teams = $this->teams();

        $selectedSeason = $this->selectedSeasonId
            ? Season::find($this->selectedSeasonId)
            : Season::current();

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Interclubs', '#')
                ->current(__('Our teams'))
                ->toArray(),
            'filterChips' => $this->filterChips,
            'teams' => $teams,
            'season' => $selectedSeason,
            'seasons' => Season::orderBy('start_at')->get(),
            'teamsCount' => $teams->count(),
            'teamNameOptions' => collect(TeamName::cases())->map(fn ($t) => ['id' => $t->name, 'name' => $t->name]),
            'categoryOptions' => collect(LeagueCategory::cases())->map(fn ($c) => ['id' => $c->name, 'name' => $c->value]),
            'levelOptions' => collect(LeagueLevel::cases())->map(fn ($l) => ['id' => $l->name, 'name' => $l->value]),
            'isAdminOrCommittee' => Auth::user()->is_admin || Auth::user()->is_committee_member,
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Teams'));
    }
};
