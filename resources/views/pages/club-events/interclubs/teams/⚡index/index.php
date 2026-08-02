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
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TeamName;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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

    /** Bascule vers la saisie d'une nouvelle division plutôt que le choix d'une existante. */
    public bool $newDivisionMode = false;

    public ?int $newLeagueId = null;

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
        Gate::authorize(Permission::TeamsManage->value);

        $this->teamToDelete = $id;
        $this->deleteModal = true;
    }

    public function createTeam(): void
    {
        Gate::authorize(Permission::TeamsManage->value);

        $season = Season::current();

        // La validation passe avant le contrôle de saison : sans elle, un
        // formulaire vide sans saison active ne signalerait aucun champ manquant.
        //
        // Deux chemins distincts : choisir une division existante, ou en créer une
        // explicitement. Auparavant un firstOrCreate implicite créait une division
        // à la moindre faute de frappe (issue #27).
        $rules = ['newTeamName' => ['required', 'string']];
        $messages = ['newTeamName.required' => 'Choisissez une lettre pour l\'équipe.'];

        if ($this->newDivisionMode) {
            $rules += [
                'newCategory' => ['required', 'string'],
                'newLevel' => ['required', 'string'],
                'newDivision' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,4}$/'],
            ];
            $messages += [
                'newCategory.required' => __('Please select a category.'),
                'newLevel.required' => __('Please select a level.'),
                'newDivision.required' => 'Indiquez la division.',
                'newDivision.regex' => __('A division is 1 to 4 letters or digits, for example 3B.'),
            ];
        } else {
            $rules += [
                'newLeagueId' => [
                    'required',
                    Rule::exists('leagues', 'id')->where('season_id', $season?->id),
                ],
            ];
            $messages += ['newLeagueId.required' => __('Please select a division.')];
        }

        $this->validate($rules, $messages);

        if (! $season) {
            $this->error('Aucune saison active.');

            return;
        }

        $ourClub = Club::own();

        $league = $this->newDivisionMode
            ? League::firstOrCreate([
                'category' => $this->newCategory,
                'level' => $this->newLevel,
                'division' => strtoupper($this->newDivision),
                'season_id' => $season->id,
            ])
            : League::findOrFail($this->newLeagueId);

        Team::create([
            'name' => $this->newTeamName,
            'season_id' => $season->id,
            'league_id' => $league->id,
            'club_id' => $ourClub?->id,
        ]);

        $this->reset('newTeamName', 'newCategory', 'newLevel', 'newDivision', 'newLeagueId', 'newDivisionMode');
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
        Gate::authorize(Permission::TeamsManage->value);

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
                $nextMatch = $nextMatches->first(fn (Interclub $ic): bool => $ic->visited_team_id === $team->id || $ic->visiting_team_id === $team->id
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
                fn ($team): bool => str_contains(strtolower($team->name), strtolower($this->search))
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
            'teamNameOptions' => collect(TeamName::cases())->map(fn ($t): array => ['id' => $t->name, 'name' => $t->name]),
            'categoryOptions' => collect(LeagueCategory::cases())->map(fn ($c): array => ['id' => $c->name, 'name' => $c->value]),
            'levelOptions' => collect(LeagueLevel::cases())->map(fn ($l): array => ['id' => $l->name, 'name' => $l->value]),
            'leagueOptions' => $this->leagueOptions(Season::current()),
            'isAdminOrCommittee' => Auth::user()->can(Permission::TeamsManage->value),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Teams'));
    }

    /**
     * Divisions déjà déclarées pour la saison, libellées niveau – division – catégorie.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    protected function leagueOptions(?Season $season): Collection
    {
        if ($season === null) {
            return collect();
        }

        $levelLabels = array_column(LeagueLevel::cases(), 'value', 'name');

        return League::where('season_id', $season->id)
            ->orderBy('level')
            ->orderBy('division')
            ->get()
            ->map(fn (League $league): array => [
                'id' => $league->id,
                'name' => implode(' – ', array_filter([
                    $levelLabels[$league->level] ?? $league->level,
                    $league->division,
                    $league->category,
                ])),
            ]);
    }
};
