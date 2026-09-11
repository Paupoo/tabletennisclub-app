<?php

declare(strict_types=1);

namespace Resources\views\Pages\ClubEvents\Interclubs\Teams\Edit;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TeamName;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithPagination;

    /** Assez pour balayer une page sans dérouler, assez peu pour rester lisible. */
    private const int CANDIDATES_PER_PAGE = 15;

    public ?int $captainId = null;

    /**
     * Options du sélecteur de capitaine (maryUI x-choices appelle `search()`).
     *
     * @var array<int, array{id: int, name: string}>
     */
    public array $captainOptions = [];

    /**
     * Moves the operator has agreed to, kept out of the database until the form
     * is saved: confirming detaches the player on the form, not in the season.
     * Writing straight away would let « Annuler » leave them in no team at all.
     *
     * A list rather than a map keyed by user id — Livewire hydrates integer keys
     * back as strings, and this array crosses the wire on every request.
     *
     * @var array<int, array{userId: int, teamId: int}>
     */
    public array $confirmedMoves = [];

    public ?int $leagueId = null;

    public array $memberIds = [];

    public string $memberSearch = '';

    public string $name = '';

    public string $newCategory = '';

    public string $newDivision = '';

    /** Bascule vers la saisie d'une nouvelle division plutôt que le choix d'une existante. */
    public bool $newDivisionMode = false;

    public string $newLevel = '';

    /**
     * The move awaiting an answer in the modal.
     *
     * @var array{userId: int, teamId: int, teamName: string, playerName: string}|null
     */
    public ?array $pendingMove = null;

    /** Le drapeau que <x-confirm-modal> pilote ; `pendingMove` en porte le contenu. */
    public bool $showMoveModal = false;

    #[Locked]
    public int $teamId;

    public function cancelMove(): void
    {
        $this->pendingMove = null;
        $this->showMoveModal = false;
    }

    public function confirmMove(): void
    {
        if ($this->pendingMove === null) {
            return;
        }

        $this->confirmedMoves[] = [
            'userId' => $this->pendingMove['userId'],
            'teamId' => $this->pendingMove['teamId'],
        ];
        $this->memberIds[] = $this->pendingMove['userId'];
        $this->pendingMove = null;
        $this->showMoveModal = false;
    }

    public function mount(Team $team): void
    {
        Gate::authorize(Permission::TeamsManage->value);

        $this->teamId = $team->id;
        $this->name = $team->name;
        $this->captainId = $team->captain_id;
        $this->leagueId = $team->league_id;
        $this->memberIds = $team->users->pluck('id')->toArray();

        $this->search();
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
            'memberIds' => ['array'],
        ];
        $messages = [
            'name.size' => __('The name must be a single letter (A–Z).'),
            'memberIds.min' => 'L\'équipe doit avoir au moins un joueur.',
        ];

        // Une équipe naît sans joueur : la liste la crée avec sa seule lettre et
        // sa division. Exiger un noyau ici bloquerait toute correction tant que
        // personne n'y est inscrit — le formulaire refusait d'enregistrer sans
        // rien afficher. On protège seulement contre le vidage d'un noyau
        // déjà constitué.
        if ($team->users()->exists()) {
            $rules['memberIds'][] = 'min:1';
        }

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

        // Renommer l'équipe peut heurter la clé d'identité — une lettre par
        // catégorie — que le modèle tient. Le formulaire la traduit sur le champ
        // concerné plutôt que de laisser l'écran tomber.
        try {
            $this->applyRoster($team);
        } catch (\DomainException $exception) {
            $this->addError('name', $exception->getMessage());

            return;
        }

        $this->confirmedMoves = [];

        $this->success(
            'Équipe mise à jour',
            redirectTo: route('admin.interclubs.teams.show', $this->teamId)
        );
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

    /**
     * Captaining is not playing.
     *
     * Naming a captain used to add them to the core, which made the two
     * indistinguishable: one could not captain a team without taking a place in
     * it, and taking a place in a second team of the category is exactly what the
     * exclusivity rule now refuses. The club does need the two apart — an adult
     * standing in front of a team they do not play in — so the link is cut here.
     */
    /**
     * Alimente le sélecteur de capitaine.
     *
     * Le vivier est le club entier — capitainer n'est pas jouer, et un bénévole
     * non affilié rend le même service qu'un joueur du noyau. Les archivés en
     * sortent d'eux-mêmes : `User` porte `SoftDeletes`.
     *
     * Recherche vide : le noyau d'abord, parce que le capitaine en sort neuf fois
     * sur dix et qu'on ne doit pas avoir à taper pour le cas courant. Le capitaine
     * déjà nommé reste toujours dans la liste, sans quoi le choix disparaîtrait de
     * l'écran dès la recherche suivante.
     */
    public function search(string $value = ''): void
    {
        $selected = $this->captainId !== null
            ? User::whereKey($this->captainId)->get()
            : collect();

        $core = $value === ''
            ? User::whereIn('id', $this->memberIds)->orderBy('last_name')->orderBy('first_name')->get()
            : collect();

        $matches = User::query()
            ->when($value !== '', fn (Builder $query) => $query->searchName($value))
            ->when($value === '' && $this->memberIds !== [], fn (Builder $query) => $query->whereNotIn('id', $this->memberIds))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->take(10)
            ->get();

        $this->captainOptions = $core
            ->concat($matches)
            ->concat($selected)
            ->unique('id')
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->full_name])
            ->values()
            ->all();
    }

    public function setCaptain(int $userId): void
    {
        $this->captainId = $userId;
    }

    /**
     * Picking somebody who already holds a core in this category is not a mistake
     * to refuse — it is an intention badly expressed. Nobody wants a player in two
     * cores at once; they want them to change team. So the click offers the move.
     */
    public function toggleMember(int $userId): void
    {
        if (in_array($userId, $this->memberIds)) {
            $this->memberIds = array_values(array_filter($this->memberIds, fn ($id): bool => $id !== $userId));
            $this->confirmedMoves = array_values(array_filter(
                $this->confirmedMoves,
                fn (array $move): bool => $move['userId'] !== $userId
            ));

            return;
        }

        $held = $this->teamHolding($userId);

        if ($held instanceof Team) {
            $player = User::find($userId);

            $this->pendingMove = [
                'userId' => $userId,
                'teamId' => $held->id,
                'teamName' => $held->name,
                'playerName' => trim($player?->first_name . ' ' . $player?->last_name),
            ];
            $this->showMoveModal = true;

            return;
        }

        $this->memberIds[] = $userId;
    }

    /**
     * Changer la recherche remet en page 1 : sinon on cherche un nom et on tombe
     * sur une page vide, la pagination gardant son rang d'avant.
     */
    public function updatedMemberSearch(): void
    {
        $this->resetPage();
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
        // `orderBy` sur la colonne de liste de force remonte les NULL en tête en
        // MySQL : les joueurs sans position passaient devant ceux qui en ont une,
        // et la liste semblait n'être triée par rien. Le compositeur d'équipes
        // réglait déjà ce cas ainsi ; cet écran ne l'avait jamais fait.
        $forceColumn = User::forceListColumn($category);

        $competitors = User::interclubEligible()
            ->when($category === Gender::WOMEN->value, fn ($q) => $q->where('gender', Gender::WOMEN->value))
            ->when($category === 'VETERANS', fn ($q) => $q->veteran($season))
            ->when($this->memberSearch, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$this->memberSearch}%")
                ->orWhere('last_name', 'like', "%{$this->memberSearch}%")
            ))
            ->orderByRaw("CASE WHEN {$forceColumn} IS NULL THEN 1 ELSE 0 END")
            ->orderBy($forceColumn)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(self::CANDIDATES_PER_PAGE);

        $teamNameOptions = collect(TeamName::cases())
            ->map(fn ($n): array => ['id' => $n->name, 'name' => $n->name]);

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

        // Qui tient déjà un noyau de cette catégorie, et dans quelle équipe. Le
        // sélectionneur doit le voir sur la ligne : masquer ces joueurs les ferait
        // passer pour inéligibles, et l'écran a déjà appris ailleurs qu'une liste
        // qui rétrécit sans rien dire est une liste qu'on croit cassée.
        $heldElsewhere = DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->leftJoin('leagues', 'leagues.id', '=', 'teams.league_id')
            ->where('teams.season_id', $team->season_id)
            ->where('teams.id', '!=', $team->id)
            ->when(
                $category === null,
                fn ($query) => $query->whereNull('leagues.category'),
                fn ($query) => $query->where('leagues.category', $category),
            )
            ->pluck('teams.name', 'team_user.user_id')
            ->all();
        // Le capitaine affiché vient de la table, pas du vivier des compétiteurs :
        // un bénévole n'y figure pas, et son nom doit s'afficher dès qu'on le choisit.
        $captainUser = $this->captainId === null ? null : User::find($this->captainId);

        // Deux murs se dressent entre la nomination et la première composition, et
        // aucun ne se signale : la route interclubs exige un e-mail vérifié, et
        // EnsureProfileIsComplete renvoie vers l'assistant tant que la fiche est
        // incomplète. On ne bloque pas — on évite que ça se découvre par téléphone.
        $captainNeedsEmailConfirmation = $captainUser !== null && $captainUser->email_verified_at === null;
        $captainNeedsProfile = $captainUser !== null && ! $captainUser->hasCompleteProfile();

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
            'captainUser' => $captainUser,
            'captainNeedsEmailConfirmation' => $captainNeedsEmailConfirmation,
            'captainNeedsProfile' => $captainNeedsProfile,
            'teamNameOptions' => $teamNameOptions,
            'leagueOptions' => $leagueOptions,
            'scheduledMatchCount' => $scheduledMatchCount,
            'heldElsewhere' => $heldElsewhere,
            'categoryOptions' => collect(LeagueCategory::cases())->map(fn ($c): array => ['id' => $c->name, 'name' => $c->value]),
            'levelOptions' => collect(LeagueLevel::cases())->map(fn ($l): array => ['id' => $l->name, 'name' => $l->value]),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Edit Team'));
    }

    /**
     * Les deux écritures vont de pair : détacher l'équipe d'origine puis rattacher
     * ici. Séparées, une panne entre les deux laisse le joueur sans aucune équipe —
     * exactement ce que la garde du pivot ne peut pas rattraper.
     */
    /**
     * Écrit la composition : les déplacements confirmés, puis le noyau.
     *
     * Les deux écritures vont de pair — détacher l'équipe d'origine, rattacher
     * ici — d'où la transaction. Séparées, une panne entre les deux laisserait le
     * joueur sans aucune équipe, ce que la garde du pivot ne peut pas rattraper :
     * elle refuse un noyau de trop, jamais un noyau manquant.
     */
    private function applyRoster(Team $team): void
    {
        DB::transaction(function () use ($team): void {
            $team->save();

            foreach ($this->confirmedMoves as $move) {
                if (! in_array($move['userId'], $this->memberIds)) {
                    continue;
                }

                Team::find($move['teamId'])?->users()->detach($move['userId']);
            }

            $team->users()->sync($this->memberIds);
        });
    }

    /**
     * The team already holding this player for the season and category of the team
     * being edited, if any.
     */
    private function teamHolding(int $userId): ?Team
    {
        $team = Team::with('league')->findOrFail($this->teamId);

        return Team::coreHeldBy(
            $userId,
            $team->season_id,
            $team->league?->category,
            exceptTeamId: $team->id,
        );
    }
};
