<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams\Builder;

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Ranking;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public int $nucleusSize = 6;

    // ── Étape 2 : distribution proposée ─────────────────────────────────────
    /**
     * Structure : [['letter'=>'A','players'=>[userId,...], 'captainId'=>null, 'category'=>'', 'level'=>'', 'division'=>''], ...]
     *
     * @var array<int, array{letter: string, players: int[], captainId: int|null, category: string, level: string, division: string}>
     */
    public array $proposedTeams = [];

    public ?int $seasonId = null;

    public bool $showComputingModal = false;

    // ── Étape 1 : paramètres ─────────────────────────────────────────────────
    public int $step = 1;

    /** 'MEN' | 'WOMEN' | 'VETERANS' */
    public string $teamCategory = 'MEN';

    /**
     * Joueurs sans équipe (surplus).
     *
     * @var int[]
     */
    public array $unassigned = [];

    public function backToStep1(): void
    {
        $this->step = 1;
        $this->proposedTeams = [];
        $this->unassigned = [];
    }

    public function computeDistribution(): void
    {
        RecalculateForceListAction::handle();

        $competitors = $this->buildEligibleQuery()->get();
        $totalTeams = intdiv($competitors->count(), $this->nucleusSize);

        if ($totalTeams === 0) {
            $this->error("Pas assez de compétiteurs éligibles ({$competitors->count()}) pour former une équipe de {$this->nucleusSize}.");

            return;
        }

        $names = $this->teamNameSequence($totalTeams);
        $playerChunks = $competitors->chunk($this->nucleusSize);

        $this->proposedTeams = collect($names)->values()->map(fn (string $name, int $i): array => [
            'letter' => $name,
            'players' => $playerChunks->get($i)?->pluck('id')->toArray() ?? [],
            'captainId' => null,
            'category' => $this->teamCategory,
            'level' => '',
            'division' => '',
        ])->toArray();

        $assignedIds = collect($this->proposedTeams)->flatMap(fn ($t): mixed => $t['players'])->toArray();
        $this->unassigned = $competitors->whereNotIn('id', $assignedIds)->pluck('id')->toArray();

        $this->sortAllTeams();

        $this->showComputingModal = false;
        $this->step = 2;
    }

    public function mount(): void
    {
        Gate::authorize(Permission::TeamsManage->value);

        $this->seasonId = Season::current()?->id;
    }

    // ── Déplacement d'un joueur entre équipes (drag & drop) ──────────────────

    public function movePlayerToTeam(int $userId, int $teamIndex): void
    {
        foreach ($this->proposedTeams as &$team) {
            $team['players'] = array_values(array_filter(
                $team['players'],
                fn (int $id): bool => $id !== $userId
            ));
        }

        $this->unassigned = array_values(array_filter(
            $this->unassigned,
            fn (int $id): bool => $id !== $userId
        ));

        $this->proposedTeams[$teamIndex]['players'][] = $userId;

        $this->sortAllTeams();
    }

    public function movePlayerToUnassigned(int $userId): void
    {
        foreach ($this->proposedTeams as &$team) {
            // Si le joueur était capitaine de cette équipe, retirer le titre
            if (($team['captainId'] ?? null) === $userId) {
                $team['captainId'] = null;
            }

            $team['players'] = array_values(array_filter(
                $team['players'],
                fn (int $id): bool => $id !== $userId
            ));
        }

        if (! in_array($userId, $this->unassigned)) {
            $this->unassigned[] = $userId;
        }

        $this->unassigned = $this->sortByRanking($this->unassigned);
    }

    public function render(): View
    {
        return $this->view();
    }

    // ── Sauvegarde ────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'seasonId' => ['required', 'exists:seasons,id'],
            'proposedTeams' => ['required', 'array', 'min:1'],
            'proposedTeams.*.letter' => ['required', 'string'],
            'proposedTeams.*.category' => ['required', 'string'],
            'proposedTeams.*.level' => ['required', 'string'],
            'proposedTeams.*.division' => ['required', 'string'],
        ], [
            'proposedTeams.*.category.required' => __('Set the category for each team.'),
            'proposedTeams.*.level.required' => __('Set the level for each team.'),
            'proposedTeams.*.division.required' => __('Set the division for each team.'),
        ]);

        $ourClub = Club::own();

        foreach ($this->proposedTeams as $data) {
            $league = League::firstOrCreate([
                'category' => $data['category'],
                'level' => $data['level'],
                'division' => $data['division'],
                'season_id' => $this->seasonId,
            ]);

            $team = Team::create([
                'name' => $data['letter'],
                'season_id' => $this->seasonId,
                'league_id' => $league->id,
                'club_id' => $ourClub?->id,
                'captain_id' => $data['captainId'] ?? null,
            ]);

            $team->users()->sync($data['players']);
        }

        $this->success(
            count($this->proposedTeams) . ' équipes créées !',
            'La composition de la saison a été enregistrée.',
            redirectTo: route('admin.interclubs.teams')
        );
    }

    // ── Capitaine ────────────────────────────────────────────────────────────

    public function setCaptainInTeam(int $teamIndex, int $userId): void
    {
        $current = $this->proposedTeams[$teamIndex]['captainId'] ?? null;
        $this->proposedTeams[$teamIndex]['captainId'] = ($current === $userId) ? null : $userId;
    }

    // ── Étape 1 → 2 : calcul de la distribution ──────────────────────────────

    public function startComputing(): void
    {
        $this->validate([
            'seasonId' => ['required', 'exists:seasons,id'],
            'nucleusSize' => ['required', 'integer', 'min:5', 'max:20'],
        ], [
            'seasonId.required' => __('Please select a season.'),
            'nucleusSize.min' => 'Le noyau minimum est de 5 joueurs.',
        ]);

        $this->showComputingModal = true;
        $this->js('$wire.computeDistribution()');
    }

    public function with(): array
    {
        $allCompetitors = User::interclubEligible()
            ->orderBy(User::forceListColumn($this->teamCategory))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->keyBy('id');

        return [
            'breadcrumbs' => Breadcrumb::make()
                ->home()
                ->add('Interclubs', '#')
                ->add('Équipes', route('admin.interclubs.teams'))
                ->current('Compositeur')
                ->toArray(),
            'seasons' => Season::orderByDesc('start_at')->get(),
            'competitors' => $allCompetitors,
            'eligibleCount' => $this->buildEligibleQuery()->count(),
            'missingBirthdateCount' => $this->teamCategory === 'VETERANS'
                ? User::interclubEligible()->whereNull('birthdate')->count()
                : 0,
            'categoryOptions' => collect(LeagueCategory::cases())->map(fn ($c): array => ['id' => $c->name, 'name' => $c->value]),
            'levelOptions' => collect(LeagueLevel::cases())->map(fn ($l): array => ['id' => $l->name, 'name' => $l->value]),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Teams Builder'));
    }

    // ── Données pour la vue ───────────────────────────────────────────────────

    private function buildEligibleQuery(): Builder
    {
        // Tri par la liste de force de la catégorie (générale / dames / vétérans),
        // les positions définies en premier, sinon repli sur le classement.
        $forceColumn = User::forceListColumn($this->teamCategory);

        $query = User::interclubEligible()
            ->orderByRaw("CASE WHEN {$forceColumn} IS NULL THEN 1 ELSE 0 END")
            ->orderBy($forceColumn)
            ->orderBy('ranking')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($this->teamCategory === Gender::WOMEN->value) {
            $query->where('gender', Gender::WOMEN->value);
        } elseif ($this->teamCategory === 'VETERANS') {
            $season = $this->seasonId ? Season::find($this->seasonId) : Season::current();
            $query->veteran($season);
        }

        return $query;
    }

    private function sortAllTeams(): void
    {
        foreach ($this->proposedTeams as &$team) {
            $team['players'] = $this->sortByRanking($team['players']);
        }

        $this->unassigned = $this->sortByRanking($this->unassigned);
    }

    // ── Tri par classement ───────────────────────────────────────────────────

    /**
     * Trie un tableau d'IDs par classement (ordre alphabétique : A1 → NC).
     *
     * @param  int[]  $playerIds
     * @return int[]
     */
    private function sortByRanking(array $playerIds): array
    {
        if (count($playerIds) < 2) {
            return $playerIds;
        }

        // `ranking` est casté en enum : on repasse par sa valeur pour comparer
        // des chaînes, et 'ZZ' garde les joueurs sans classement en fin de liste.
        $rankings = User::whereIn('id', $playerIds)
            ->pluck('ranking', 'id')
            ->map(fn (?Ranking $ranking): string => $ranking?->value ?? 'ZZ');

        usort($playerIds, fn (int $a, int $b): int => strcmp(
            $rankings[$a] ?? 'ZZ',
            $rankings[$b] ?? 'ZZ'
        ));

        return $playerIds;
    }

    // ── Nommage des équipes ──────────────────────────────────────────────────

    /**
     * Generates team name sequence: A–Z, then 1, 2, 3 … for counts above 26.
     *
     * @return string[]
     */
    private function teamNameSequence(int $count): array
    {
        $letters = array_map(fn (int $i): string => chr(ord('A') + $i), range(0, 25));
        $numbers = array_map(fn (int $i): string => (string) $i, range(1, max(0, $count - 26)));

        return array_slice(array_merge($letters, $numbers), 0, $count);
    }
};
