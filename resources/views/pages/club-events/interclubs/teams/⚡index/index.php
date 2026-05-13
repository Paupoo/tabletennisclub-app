<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\TeamName;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    public string $search = '';

    public bool $deleteModal    = false;
    public bool $deleteAllModal = false;
    public bool $createModal    = false;
    public ?int $teamToDelete   = null;

    public string $newTeamName = '';
    public string $newCategory = '';
    public string $newLevel    = '';
    public string $newDivision = '';

    public function render(): View
    {
        return $this->view();
    }

    public function createTeam(): void
    {
        $this->validate([
            'newTeamName' => ['required', 'string'],
            'newCategory' => ['required', 'string'],
            'newLevel'    => ['required', 'string'],
            'newDivision' => ['required', 'string'],
        ], [
            'newTeamName.required' => 'Choisissez une lettre pour l\'équipe.',
            'newCategory.required' => 'Sélectionnez une catégorie.',
            'newLevel.required'    => 'Sélectionnez un niveau.',
            'newDivision.required' => 'Indiquez la division.',
        ]);

        $season = Season::current();

        if (! $season) {
            $this->error('Aucune saison active.');

            return;
        }

        $ourClub = Club::where('licence', config('app.club_licence'))->first();

        $league = League::firstOrCreate([
            'category'  => $this->newCategory,
            'level'     => $this->newLevel,
            'division'  => $this->newDivision,
            'season_id' => $season->id,
        ]);

        Team::create([
            'name'      => $this->newTeamName,
            'season_id' => $season->id,
            'league_id' => $league->id,
            'club_id'   => $ourClub?->id,
        ]);

        $this->reset('newTeamName', 'newCategory', 'newLevel', 'newDivision');
        $this->createModal = false;

        $this->success('Équipe créée.');
    }

    public function confirmDelete(int $id): void
    {
        $this->teamToDelete  = $id;
        $this->deleteModal   = true;
    }

    public function delete(): void
    {
        $team = Team::findOrFail($this->teamToDelete);
        $team->users()->detach();
        $team->delete();

        $this->teamToDelete = null;
        $this->deleteModal  = false;

        $this->success('Équipe supprimée.');
    }

    public function deleteAll(): void
    {
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

    public function teams(): Collection
    {
        $season = Season::current();

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
            'MEN'      => 'Hommes',
            'VETERANS' => 'Vétérans',
            'WOMEN'    => 'Dames',
        ];

        return $teams
            ->map(function (Team $team) use ($nextMatches, $categoryLabels) {
                $nextMatch = $nextMatches->first(fn (Interclub $ic) =>
                    $ic->visited_team_id === $team->id || $ic->visiting_team_id === $team->id
                );

                $rawCategory = $team->league?->category ?? '';
                $category    = $categoryLabels[$rawCategory] ?? $rawCategory;

                $division = implode(' – ', array_filter([
                    $team->league?->level,
                    $team->league?->division,
                ]));

                return (object) [
                    'id'            => $team->id,
                    'name'          => trim(($team->club?->name ?? '') . ' ' . $team->name),
                    'teamLetter'    => $team->name,
                    'division'      => $division ?: '—',
                    'category'      => $category,
                    'captain_name'  => $team->captain
                        ? $team->captain->first_name . ' ' . $team->captain->last_name
                        : '—',
                    'membersCount'  => $team->users->count(),
                    'rank'          => null,
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

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Interclubs', '#')
                ->current(__('Our teams'))
                ->toArray(),
            'teams'           => $teams,
            'season'          => Season::current(),
            'teamsCount'      => $teams->count(),
            'teamNameOptions' => collect(TeamName::cases())->map(fn ($t) => ['id' => $t->name, 'name' => $t->name]),
            'categoryOptions' => collect(LeagueCategory::cases())->map(fn ($c) => ['id' => $c->name, 'name' => $c->value]),
            'levelOptions'    => collect(LeagueLevel::cases())->map(fn ($l) => ['id' => $l->name, 'name' => $l->value]),
        ];
    }
};
