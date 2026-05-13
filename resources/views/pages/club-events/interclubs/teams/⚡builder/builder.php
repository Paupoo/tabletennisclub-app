<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams\Builder;

use App\Enums\Gender;
use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\TeamName;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use Toast;

    // ── Étape 1 : paramètres ─────────────────────────────────────────────────
    public int $step = 1;

    public ?int $seasonId = null;

    public int $nucleusSize = 6;

    /** 'MEN' | 'WOMEN' | 'VETERANS' */
    public string $teamCategory = 'MEN';

    // ── Étape 2 : distribution proposée ─────────────────────────────────────
    /**
     * Structure : [['letter'=>'A','players'=>[userId,...], 'captainId'=>null, 'category'=>'', 'level'=>'', 'division'=>''], ...]
     *
     * @var array<int, array{letter: string, players: int[], captainId: int|null, category: string, level: string, division: string}>
     */
    public array $proposedTeams = [];

    /**
     * Joueurs sans équipe (surplus).
     *
     * @var int[]
     */
    public array $unassigned = [];

    public function mount(): void
    {
        $this->seasonId = Season::current()?->id;
    }

    public function render(): View
    {
        return $this->view();
    }

    // ── Étape 1 → 2 : calcul de la distribution ──────────────────────────────

    public function computeDistribution(): void
    {
        $this->validate([
            'seasonId'    => ['required', 'exists:seasons,id'],
            'nucleusSize' => ['required', 'integer', 'min:5', 'max:20'],
        ], [
            'seasonId.required' => 'Sélectionnez une saison.',
            'nucleusSize.min'   => 'Le noyau minimum est de 5 joueurs.',
        ]);

        $competitors = $this->buildEligibleQuery()->get();
        $totalTeams  = intdiv($competitors->count(), $this->nucleusSize);

        if ($totalTeams === 0) {
            $this->error("Pas assez de compétiteurs éligibles ({$competitors->count()}) pour former une équipe de {$this->nucleusSize}.");

            return;
        }

        $teams        = collect(TeamName::cases())->take($totalTeams);
        $playerChunks = $competitors->chunk($this->nucleusSize);

        $this->proposedTeams = $teams->values()->map(fn ($teamName, int $i) => [
            'letter'    => $teamName->name,
            'players'   => $playerChunks->get($i)?->pluck('id')->toArray() ?? [],
            'captainId' => null,
            'category'  => $this->teamCategory,
            'level'     => '',
            'division'  => '',
        ])->toArray();

        $assignedIds      = collect($this->proposedTeams)->flatMap(fn ($t) => $t['players'])->toArray();
        $this->unassigned = $competitors->whereNotIn('id', $assignedIds)->pluck('id')->toArray();

        $this->sortAllTeams();

        $this->step = 2;
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

        $rankings = User::whereIn('id', $playerIds)->pluck('ranking', 'id');

        usort($playerIds, fn (int $a, int $b) => strcmp(
            $rankings[$a] ?? 'ZZ',
            $rankings[$b] ?? 'ZZ'
        ));

        return $playerIds;
    }

    private function sortAllTeams(): void
    {
        foreach ($this->proposedTeams as &$team) {
            $team['players'] = $this->sortByRanking($team['players']);
        }

        $this->unassigned = $this->sortByRanking($this->unassigned);
    }

    // ── Capitaine ────────────────────────────────────────────────────────────

    public function setCaptainInTeam(int $teamIndex, int $userId): void
    {
        $current = $this->proposedTeams[$teamIndex]['captainId'] ?? null;
        $this->proposedTeams[$teamIndex]['captainId'] = ($current === $userId) ? null : $userId;
    }

    // ── Déplacement d'un joueur entre équipes (drag & drop) ──────────────────

    public function movePlayerToTeam(int $userId, int $teamIndex): void
    {
        foreach ($this->proposedTeams as &$team) {
            $team['players'] = array_values(array_filter(
                $team['players'],
                fn (int $id) => $id !== $userId
            ));
        }

        $this->unassigned = array_values(array_filter(
            $this->unassigned,
            fn (int $id) => $id !== $userId
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
                fn (int $id) => $id !== $userId
            ));
        }

        if (! in_array($userId, $this->unassigned)) {
            $this->unassigned[] = $userId;
        }

        $this->unassigned = $this->sortByRanking($this->unassigned);
    }

    // ── Sauvegarde ────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'seasonId'                      => ['required', 'exists:seasons,id'],
            'proposedTeams'                 => ['required', 'array', 'min:1'],
            'proposedTeams.*.letter'        => ['required', 'string'],
            'proposedTeams.*.category'      => ['required', 'string'],
            'proposedTeams.*.level'         => ['required', 'string'],
            'proposedTeams.*.division'      => ['required', 'string'],
        ], [
            'proposedTeams.*.category.required' => 'Définissez la catégorie de chaque équipe.',
            'proposedTeams.*.level.required'    => 'Définissez le niveau de chaque équipe.',
            'proposedTeams.*.division.required' => 'Définissez la division de chaque équipe.',
        ]);

        $ourClub = Club::where('licence', config('app.club_licence'))->first();

        foreach ($this->proposedTeams as $data) {
            $league = League::firstOrCreate([
                'category'  => $data['category'],
                'level'     => $data['level'],
                'division'  => $data['division'],
                'season_id' => $this->seasonId,
            ]);

            $team = Team::create([
                'name'       => $data['letter'],
                'season_id'  => $this->seasonId,
                'league_id'  => $league->id,
                'club_id'    => $ourClub?->id,
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

    public function backToStep1(): void
    {
        $this->step          = 1;
        $this->proposedTeams = [];
        $this->unassigned    = [];
    }

    // ── Données pour la vue ───────────────────────────────────────────────────

    private function buildEligibleQuery(): Builder
    {
        $query = User::where('is_competitor', true)
            // force_list (admin override) en premier quand défini, sinon tri par classement
            ->orderByRaw('CASE WHEN force_list IS NULL THEN 1 ELSE 0 END')
            ->orderBy('force_list')
            ->orderBy('ranking')
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($this->teamCategory === Gender::WOMEN->value) {
            $query->where('gender', Gender::WOMEN->value);
        } elseif ($this->teamCategory === 'VETERANS') {
            $season = $this->seasonId ? Season::find($this->seasonId) : Season::current();

            if ($season) {
                $cutoff = $season->end_at->copy()->subYears(40);
                $query->whereNotNull('birthdate')->where('birthdate', '<=', $cutoff->toDateString());
            } else {
                $query->whereRaw('1 = 0'); // aucune saison = aucun résultat
            }
        }

        return $query;
    }

    public function with(): array
    {
        $allCompetitors = User::where('is_competitor', true)
            ->orderBy('force_list')
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
            'seasons'           => Season::orderByDesc('start_at')->get(),
            'competitors'       => $allCompetitors,
            'eligibleCount'          => $this->buildEligibleQuery()->count(),
            'missingBirthdateCount'  => $this->teamCategory === 'VETERANS'
                ? User::where('is_competitor', true)->whereNull('birthdate')->count()
                : 0,
            'categoryOptions'   => collect(LeagueCategory::cases())->map(fn ($c) => ['id' => $c->name, 'name' => $c->value]),
            'levelOptions'      => collect(LeagueLevel::cases())->map(fn ($l) => ['id' => $l->name, 'name' => $l->value]),
        ];
    }
};
