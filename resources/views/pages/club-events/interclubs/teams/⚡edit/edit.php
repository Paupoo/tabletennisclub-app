<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams\Edit;

use App\Domains\Shared\Enums\Permission;
use Illuminate\Support\Facades\Gate;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use App\Domains\Shared\Enums\TeamName;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public ?int $captainId = null;

    /** Bascule vers la saisie d'une nouvelle division plutôt que le choix d'une existante. */
    public bool $newDivisionMode = false;

    public string $newCategory = '';

    public string $newDivision = '';

    public string $newLevel = '';

    public ?int $leagueId = null;

    public array $memberIds = [];

    public string $memberSearch = '';

    public string $name = '';

    #[Locked]
    public int $teamId;

    public function mount(Team $team): void
    {
        Gate::authorize(Permission::TeamsManage->value);

        $this->teamId = $team->id;
        $this->name = $team->name;
        $this->captainId = $team->captain_id;
        $this->leagueId = $team->league_id;
        $this->memberIds = $team->users->pluck('id')->toArray();
    }

    /**
     * Nombre de rencontres où l'équipe est engagée, à domicile ou en déplacement.
     *
     * Les rencontres portent leur propre league_id : déplacer l'équipe une fois
     * le calendrier encodé laisserait ces rencontres rattachées à l'ancienne
     * division. La division est donc verrouillée dès la première rencontre.
     */
    public function scheduledMatchCount(): int
    {
        return Interclub::where('visited_team_id', $this->teamId)
            ->orWhere('visiting_team_id', $this->teamId)
            ->count();
    }

    public function removeCaptain(): void
    {
        $this->captainId = null;
    }

    public function render(): View
    {
        return $this->view();
    }

    public function save(): void
    {
        $team = Team::findOrFail($this->teamId);
        $canChangeLeague = $this->scheduledMatchCount() === 0;

        // Mêmes deux chemins qu'à la création : choisir une division existante,
        // ou en créer une explicitement. Sans le second, corriger une erreur vers
        // une division pas encore déclarée resterait impossible (issue #27).
        $rules = [
            'name' => ['required', 'string', 'size:1'],
            'memberIds' => ['array', 'min:1'],
        ];
        $messages = [
            'name.size' => __('The name must be a single letter (A–Z).'),
            'memberIds.min' => 'L\'équipe doit avoir au moins un joueur.',
        ];

        if ($canChangeLeague && $this->newDivisionMode) {
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
                'leagueId' => [
                    'required',
                    Rule::exists('leagues', 'id')->where('season_id', $team->season_id),
                ],
            ];
            $messages += ['leagueId.exists' => __('This division does not belong to the team season.')];
        }

        $this->validate($rules, $messages);

        $team->name = strtoupper($this->name);
        $team->captain_id = $this->captainId;

        // Le champ est masqué côté vue quand des rencontres existent ; on refuse
        // aussi le changement côté serveur, la vue n'étant pas une protection.
        if ($canChangeLeague) {
            $team->league_id = $this->newDivisionMode
                ? League::firstOrCreate([
                    'category' => $this->newCategory,
                    'level' => $this->newLevel,
                    'division' => strtoupper($this->newDivision),
                    'season_id' => $team->season_id,
                ])->id
                : $this->leagueId;
        }

        $team->save();

        $team->users()->sync($this->memberIds);

        $this->success(
            'Équipe mise à jour',
            redirectTo: route('admin.interclubs.teams.show', $this->teamId)
        );
    }

    public function setCaptain(int $userId): void
    {
        if (! in_array($userId, $this->memberIds)) {
            $this->memberIds[] = $userId;
        }
        $this->captainId = $userId;
    }

    public function toggleMember(int $userId): void
    {
        if (in_array($userId, $this->memberIds)) {
            $this->memberIds = array_values(array_filter($this->memberIds, fn ($id) => $id !== $userId));
        } else {
            $this->memberIds[] = $userId;
        }
    }

    public function with(): array
    {
        $team = Team::with(['league', 'captain', 'users', 'club', 'season'])->findOrFail($this->teamId);
        $category = $team->league?->category;
        $season = $team->season;

        $levelLabels = array_column(LeagueLevel::cases(), 'value', 'name');
        $levelLabel = $levelLabels[$team->league?->level] ?? $team->league?->level;
        $division = implode(' – ', array_filter([$levelLabel, $team->league?->division]));

        // Membres actuels de l'équipe — toujours chargés pour le panel capitaine
        $teamMembers = User::whereIn('id', $this->memberIds)
            ->orderBy(User::forceListColumn($category))
            ->orderBy('last_name')
            ->get();

        // Liste complète des candidats éligibles, filtrée selon la catégorie de l'équipe
        $competitors = User::interclubEligible()
            ->when($category === Gender::WOMEN->value, fn ($q) => $q->where('gender', Gender::WOMEN->value))
            ->when($category === 'VETERANS', fn ($q) => $q->veteran($season))
            ->when($this->memberSearch, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$this->memberSearch}%")
                ->orWhere('last_name', 'like', "%{$this->memberSearch}%")
            ))
            ->orderBy(User::forceListColumn($category))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $teamNameOptions = collect(TeamName::cases())
            ->map(fn ($n) => ['id' => $n->name, 'name' => $n->name]);

        // Divisions déjà déclarées pour la saison de l'équipe. On ne propose que
        // l'existant : créer une division reste une action délibérée, ailleurs.
        $leagueOptions = League::where('season_id', $team->season_id)
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

        $scheduledMatchCount = $this->scheduledMatchCount();

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Interclubs', '#')
                ->add('Équipes', route('admin.interclubs.teams'))
                ->add($team->club?->name . ' ' . $team->name, route('admin.interclubs.teams.show', $team->id))
                ->current('Modifier')
                ->toArray(),
            'team' => $team,
            'division' => $division ?: '—',
            'competitors' => $competitors,
            'teamMembers' => $teamMembers,
            'teamNameOptions' => $teamNameOptions,
            'leagueOptions' => $leagueOptions,
            'scheduledMatchCount' => $scheduledMatchCount,
            'categoryOptions' => collect(LeagueCategory::cases())->map(fn ($c) => ['id' => $c->name, 'name' => $c->value]),
            'levelOptions' => collect(LeagueLevel::cases())->map(fn ($l) => ['id' => $l->name, 'name' => $l->value]),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Edit Team'));
    }
};
