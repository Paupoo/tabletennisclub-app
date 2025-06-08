<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Tournament\ToggleHasPaidTournamentAction;
use App\Enums\TournamentStatusEnum;
use App\Http\Requests\StartTournamentMatch;
use App\Http\Requests\StoreOrUpdateTournamentRequest;
use App\Models\Pool;
use App\Models\Room;
use App\Models\Table;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\TournamentFinalPhaseService;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;
use App\Services\TournamentService;
use App\Services\TournamentStatusManager;
use App\Services\TournamentTableService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class TournamentController extends Controller
{
    //
    public function __construct(
        private TournamentService $tournamentService,
        private TournamentTableService $tableService,
        private TournamentPoolService $poolService,
        private TournamentMatchService $matchService,
        private TournamentFinalPhaseService $knockoutService,
    ) {}

    /**
     * Add user to a pool
     *
     * @return Exception|Redirect
     */
    public function addUserToPool(Pool $pool, User $user): Exception|RedirectResponse
    {
        // Vérifier que l'utilisateur participe au tournoi
        if (! $user->tournaments->contains($pool->tournament_id)) {
            throw new Exception('L\'utilisateur doit être inscrit au tournoi d\'abord');
        }

        // Utiliser notre méthode personnalisée qui applique la contrainte
        $pool->attachUser($user);

        return redirect()->back()->with('Utilisateur ajouté au pool avec succès');
    }

    public function bookTableForMatch(Table $table, Tournament $tournament, TournamentMatch $match): void
    {
        $table->tournaments()
            ->updateExistingPivot($tournament->id, [
                'is_table_free' => false,
                'tournament_match_id' => $match->id,
                'match_started_at' => now(),
            ]);

        $match->table_id = $table->id;
        $match->save();
    }

    public function changeStatus(Tournament $tournament, TournamentStatusEnum $newStatus): RedirectResponse
    {
        $manager = new TournamentStatusManager($tournament);

        try {
            // code...
            $manager->setStatus($newStatus);

            return redirect()
                ->back()
                ->with('success', __('Status for tournament ' . $tournament->name . ' has been updated to ' . $newStatus->value));
        } catch (\Throwable $th) {
            return redirect()
                ->back()
                ->with('error', __($th->getMessage()));
        }
    }

    public function closeTournament(Tournament $tournament): RedirectResponse
    {
        $tournament->status = TournamentStatusEnum::CLOSED->value;
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been closed.'));
    }

    public function create(): View
    {
        return view('admin.tournaments.create', [
            'tournament' => new Tournament,
            'rooms' => Room::all(),
        ]);
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        $this->authorize('forceDelete', [$tournament]);

        $tournament->delete();

        return redirect()
            ->route('tournamentsIndex')
            ->with('success', __('The tournament ' . $tournament->name . ' has been deleted.'));
    }

    public function edit(Tournament $tournament): View
    {
        $manager = new TournamentStatusManager($tournament);

        return view('admin.tournaments.edit', [
            'tournament' => $tournament->load(['pools.users']),
            'rooms' => Room::orderBy('name')->get(),
            'statusesAllowed' => $manager->getAllowedNextStatuses(),
        ]);
    }

    /**
     * Show match edit form
     */
    public function editMatch(TournamentMatch $match): View
    {
        // Editing a match from pool or from final bracket?
        isset($match->pool->tournament)
            ? $tournament = $match->pool->tournament
            : $tournament = $match->tournament_id;

        return view('admin.tournaments.edit-match', [
            'match' => $match->load(['player1', 'player2', 'sets']),
            'pool' => $match->pool,
            'tournament' => $tournament,
        ]);
    }

    /**
     * Erase all pools from a specific tournament.
     */
    public function erasePools(Tournament $tournament): RedirectResponse
    {
        if ($tournament->status === 'pending' || $tournament->status === 'closed') {
            return redirect()
                ->back()
                ->with('error', __('You can\'t modify a tournament that is started or closed.'));
        }

        foreach ($tournament->pools as $pool) {
            $this->eraseMatches($pool);

        }

        $tournament->pools()->delete();

        return redirect()
            ->back()
            ->with('success', __('Pools have been erased successfully.'));
    }

    /**
     * Generate all matches for pools in a tournament
     */
    public function generatePoolMatches(Tournament $tournament): RedirectResponse
    {
        $results = $this->matchService->generateTournamentMatches($tournament);

        $totalMatches = 0;
        foreach ($results as $poolMatches) {
            $totalMatches += $poolMatches->count();
        }

        return redirect()
            ->back()
            ->with([
                'success' => $totalMatches . ' matches ont été générés avec succès pour toutes les poules',
            ]);
    }

    /**
     * Répartit les joueurs inscrits dans des pools
     */
    public function generatePools(Request $request, Tournament $tournament): RedirectResponse
    {
        $numberOfPools = intval($request->input('number_of_pools'));

        if ($request->input('minMatches')) {
            $numberOfPools = intdiv($tournament->total_users, $numberOfPools + 1);
        }
        // Utiliser le service pour distribuer les joueurs
        $pools = $this->poolService->distributePlayersInPools($tournament, $numberOfPools);

        return redirect()->back()->with([
            'success' => 'Joueurs répartis dans les poules avec succès',
            'pools' => $pools,
        ]);
    }

    public function getPlayersForFinalBracket(int $firstRound, Tournament $tournament)
    {
        /** On récupère le nombre de joueurs à sélectionner par poule pour se rapprocher
         *  le plus près possible du nombre de joueurs nécessaires.
         *  Puis on récupère le nombre de joueurs à repêcher
         */
        $totalPlayers = $firstRound * 2; // Il faut 2 joueurs par match, donc 32 joueurs pour un 16e de finales
        $totalPools = $tournament->pools()->count();

        $TotalQualifiedPerPool = intdiv($totalPlayers, $totalPools);
        $TotalPlayersToFill = $totalPlayers % $totalPools;

        $qualifiedPlayers = [];

        foreach ($tournament->pools() as $pool) {
            // Pour chaque poule du tournoi, on récupère les meilleurs joueurs
            $sortedPlayers = $this->matchService->calculatePoolStandings($pool);

            for ($i = 0; $i < $TotalQualifiedPerPool; $i++) {
                // On ajoute au tableau final les x premiers joueurs qualifiés
                $qualifiedPlayers[] = $sortedPlayers['players'][$i];
            }
        }

        // On obtient un tableau avec les joueurs triés par poule et par ordre dans la poule comme suit :A1, A2, B1, B2, C1, C2, D1, D2
        for ($i = 0; $i < $totalPools * $TotalQualifiedPerPool; $i++) {
            // pour chaque joueur, on prend alternativement le premier et le dernier de la liste pour faire un match (A1-D2, A2-D1, B1-C2, B2-C1...)
            if ($i % 2 === 0) {
                $qualifiedPlayers[] = array_shift($sortedPlayers);
            } else {
                $qualifiedPlayers[] = array_pop($sortedPlayers);
            }
        }

        // si le nombre de joueur manque est supérieur à 0
        if ($TotalPlayersToFill > 0) {
            // On récupère tous les joueurs inscrits au tournoi, triés par nombres de matches remportés, puis de sets, puis de points
            $sortedPlayers = $this->matchService->calculateRowStandings($tournament);

            // On retire les joueurs déjà qualifié dans le tableau précédent
            $UnqualifiedPlayers = array_diff($sortedPlayers['players'], $sortedPlayers['players']);

            for ($i = 0; $i < $TotalPlayersToFill; $i++) {
                // pour chaque joueur à repêcher, on prend le premier joueur et on l'ajoute au tableau de répêchage
                if ($i % 2 === 0) {
                    $qualifiedPlayers[] = array_shift($UnqualifiedPlayers);
                } else {
                    $qualifiedPlayers[] = array_pop($UnqualifiedPlayers);
                }
            }
        }

        // Pour chaque joueur repêché, on l'appaire avec un autre joueur pris au hasard

    }

    public function index(): View
    {
        $tournament = new Tournament;

        return view('admin.tournaments.index', [
            'rooms' => Room::orderBy('name')->get(),
            'tournament' => $tournament,
        ]);
    }

    public function publish(Tournament $tournament): RedirectResponse
    {

        foreach ($tournament->matches as $match) {
            if ($match->status === TournamentStatusEnum::PENDING->value || $match->status === TournamentStatusEnum::CLOSED) {
                return redirect()
                    ->back()
                    ->with('error', __('Tournament ' . $tournament->name . ' has pending or completed matches and can\'t be unpublished.'));
            }
        }

        $tournament->status = TournamentStatusEnum::PUBLISHED->value;
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been published.'));
    }

    public function registrerUser(Tournament $tournament, User $user): RedirectResponse
    {

        if ($this->tournamentService->IsFull($tournament)) {
            return redirect()
                ->route('tournamentShowPlayers', $tournament)
                ->with('error', 'Sorry, the tournament is full, you cannot register more players.');
        }

        // Vérifier si le joueur n'est pas déjà inscrit
        if ($tournament->users()->where('user_id', $user->id)->exists()) {
            return redirect()
                ->route('tournamentShowPlayers', $tournament)
                ->with('error', 'This player is already registered to this tournament.');
        }

        $tournament->users()
            ->attach($user);

        $this->tournamentService->countRegisteredUsers($tournament);

        return redirect()
            ->back()
            ->with('success', $user->first_name . ' ' . $user->last_name . ' has been registered to the tournament');
    }

    /**
     * Reset match results
     */
    public function resetMatch(TournamentMatch $match): RedirectResponse
    {
        $match->sets()->delete();
        $match->winner_id = null;
        $match->status = 'scheduled';
        $match->save();

        return redirect()
            ->back()
            ->with('success', 'Résultats du match réinitialisés avec succès');
    }

    public function setEndTime(Tournament $tournament, Request $request): RedirectResponse
    {
        $end_date = Carbon::createFromFormat('Y-m-d\TH:i', $request->end_date, $request->timezone ?: config('app.timezone'));

        $tournament->end_date = $end_date;
        $tournament->save();

        return redirect()
            ->route('tournament.edit', $tournament)
            ->with([
                'success' => 'End date updated successfully.',
            ]);
    }

    public function setMaxPlayers(Tournament $tournament, Request $request): RedirectResponse
    {

        // Check that we don't already have more registered players
        if ($tournament->total_users > $request->max_users) {
            return redirect()
                ->route('tournament.edit', $tournament)
                ->with([
                    'error' => 'There are already ' . $tournament->total_users - $request->max_users . ' more players registered than the limit.',
                ]);
        }
        $tournament->max_users = $request->max_users;
        $tournament->save();

        return redirect()
            ->route('tournament.edit', $tournament)
            ->with([
                'success' => 'Maximum players updated successfully',
            ]);
    }

    public function setStartTime(Tournament $tournament, Request $request): RedirectResponse
    {
        $start_date = Carbon::createFromFormat('Y-m-d\TH:i', $request->start_date, $request->timezone ?: config('app.timezone'));

        $tournament->start_date = $start_date;
        $tournament->save();

        return redirect()
            ->route('tournament.edit', $tournament)
            ->with([
                'success' => 'Start date updated successfully.',
            ]);
    }

    public function show(string $id): View
    {
        $tournament = Tournament::findorFail($id);

        $rooms = Room::orderBy('name')->get();

        $tables = $tournament
            ->tables()
            ->withPivot([
                'is_table_free',
                'match_started_at',
            ])
            ->with('match.player1', 'match.player2')
            ->orderBy('is_table_free')
            ->orderBy('match_started_at')
            ->orderByRaw('name * 1 ASC')
            ->get();

        $matches = TournamentMatch::where('tournament_id', $tournament->id)->ordered()->get();

        $unregisteredUsers = User::unregisteredUsers($tournament)->get();

        $manager = new TournamentStatusManager($tournament);

        return view('admin.tournaments.show', [
            'matches' => $matches,
            'rooms' => $rooms,
            'tables' => $tables,
            'tournament' => $tournament,
            'unregisteredUsers' => $unregisteredUsers,
            'statusesAllowed' => $manager->getAllowedNextStatuses(),
        ]);
    }

    public function showMatches(string $id): View
    {
        $tournament = Tournament::findorFail($id);

        $rooms = Room::orderBy('name')->get();

        $tables = $tournament
            ->tables()
            ->withPivot([
                'is_table_free',
                'match_started_at',
            ])
            ->with('match.player1', 'match.player2')
            ->orderBy('is_table_free')
            ->orderBy('match_started_at')
            ->orderByRaw('name * 1 ASC')
            ->get();

        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->where('round', null)
            ->ordered()
            ->paginate(50);

        $unregisteredUsers = User::unregisteredUsers($tournament)->get();

        $manager = new TournamentStatusManager($tournament);

        return view('admin.tournaments.show-matches', [
            'matches' => $matches,
            'rooms' => $rooms,
            'tables' => $tables,
            'tournament' => $tournament,
            'statusesAllowed' => $manager->getAllowedNextStatuses(),
        ]);
    }

    public function showPlayers(string $id): View
    {
        $tournament = Tournament::findorFail($id);

        $rooms = Room::orderBy('name')->get();

        $tables = $tournament
            ->tables()
            ->withPivot([
                'is_table_free',
                'match_started_at',
            ])
            ->with('match.player1', 'match.player2')
            ->orderBy('is_table_free')
            ->orderBy('match_started_at')
            ->orderByRaw('name * 1 ASC')
            ->get();

        $matches = TournamentMatch::where('tournament_id', $tournament->id)->ordered()->get();

        $users = $tournament->users()
            ->orderBy('ranking', 'asc')
            ->orderBy('last_name', 'asc')
            ->orderBy('first_name', 'asc')
            ->paginate(50);

        $manager = new TournamentStatusManager($tournament);

        return view('admin.tournaments.show-players', [
            'matches' => $matches,
            'rooms' => $rooms,
            'tables' => $tables,
            'tournament' => $tournament,
            'users' => $users,
            'statusesAllowed' => $manager->getAllowedNextStatuses(),
        ]);
    }

    /**
     * Show the pool matches view
     */
    public function showPoolMatches(Pool $pool): View
    {
        $matches = TournamentMatch::where('pool_id', $pool->id)
            ->orderBy('match_order')
            ->get();

        $standings = $this->matchService->calculatePoolStandings($pool);
        $tournament = $pool->tournament;
        $tables = $tournament->tables()
            ->wherePivot('is_table_free', true)
            ->orderByRaw('name')
            ->get();

        $manager = new TournamentStatusManager($tournament);

        return view('admin.tournaments.pool-matches', [
            'pool' => $pool,
            'tournament' => $tournament,
            'matches' => $matches,
            'standings' => $standings,
            'tables' => $tables,
            'statusesAllowed' => $manager->getAllowedNextStatuses(),
        ]);
    }

    public function showPools(string $id): View
    {
        $tournament = Tournament::with([
            'pools' => function ($query) {
                $query->orderBy('name'); // Optionnel: ordonner les pools
            },
            'pools.users' => function ($query) {
                // Charger les users de chaque pool, déjà triés par ranking
                $query->orderBy('ranking');
            },
        ])->findOrFail($id);

        $manager = new TournamentStatusManager($tournament);

        return view('admin.tournaments.show-pools', [
            'tournament' => $tournament,
            'statusesAllowed' => $manager->getAllowedNextStatuses(),
        ]);
    }

    public function showTables(string $id): View
    {
        $tournament = Tournament::findorFail($id);

        $rooms = Room::orderBy('name')->get();

        $tables = $tournament
            ->tables()
            ->withPivot([
                'is_table_free',
                'match_started_at',
            ])
            ->with('match.player1', 'match.player2')
            ->orderBy('is_table_free')
            ->orderBy('match_started_at')
            ->orderByRaw('name * 1 ASC')
            ->get();

        $matches = TournamentMatch::where('tournament_id', $tournament->id)->ordered()->get();

        $unregisteredUsers = User::unregisteredUsers($tournament)->get();

        $manager = new TournamentStatusManager($tournament);

        return view('admin.tournaments.show-tables', [
            'matches' => $matches,
            'rooms' => $rooms,
            'tables' => $tables,
            'tournament' => $tournament,
            'unregisteredUsers' => $unregisteredUsers,
            'statusesAllowed' => $manager->getAllowedNextStatuses(),
        ]);
    }

    /**
     * Start match
     */
    public function startMatch(TournamentMatch $match, StartTournamentMatch $request): RedirectResponse
    {
        $tournament = $match->tournament;

        if ($tournament->status !== TournamentStatusEnum::PENDING) {
            return redirect()
                ->back()
                ->with('error', __('Please start the tournament first'));
        }

        // TODO : check that none of the players are currently busy playing a match or being a referee
        $table = Table::find($request->table_id);
        $this->bookTableForMatch($table, $tournament, $match);
        $match->status = 'in_progress';
        $match->save();

        return redirect()
            ->back()
            ->with('success', 'Le match est en cours.');
    }

    public function startTournament(Tournament $tournament): RedirectResponse
    {
        if ($tournament->pools()->count() === 0) {
            return redirect()
                ->back()
                ->with('error', __('Please generate the pools first'));
        }

        if ($tournament->matches()->count() === 0) {
            return redirect()
                ->back()
                ->with('warning', __('Please generate the matches first'));
        }

        $tournament->status = 'pending';
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been started.'));
    }

    public function store(StoreOrUpdateTournamentRequest $request): RedirectResponse
    {
        $this->authorize('create', Tournament::class);

        $validated = $request->validated();

        $tournament = Tournament::create($validated);

        foreach ($request->room_ids as $room_id) {
            $tournament->rooms()->attach($room_id);
        }

        $this->tableService->linkAvailableTables($tournament);

        return redirect()
            ->route('tournamentsIndex')
            ->with('success', __('The tournament ' . $tournament->name . ' has been created.'));
    }

    public function toggleHasPaid(Tournament $tournament, User $user): RedirectResponse
    {
        $this->authorize('updatesBeforeStart', $tournament);
        $action = new ToggleHasPaidTournamentAction($user);
        $action->toggleHasPaid($tournament);
        
        return redirect()
            ->back();
    }

    public function unpublish(Tournament $tournament): RedirectResponse
    {
        foreach ($tournament->matches as $match) {
            if ($match->status === 'in_progress' || $match->status === 'completed') {
                return redirect()
                    ->back()
                    ->with('error', __('Tournament ' . $tournament->name . ' has pending or completed matches and can\'t be unpublished.'));
            }
        }
        $tournament->status = 'draft';
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been unpublished.'));
    }

    public function unregistrerUser(Tournament $tournament, User $user): RedirectResponse
    {
        $tournament->users()->detach($user);
        $this->tournamentService->countRegisteredUsers($tournament);

        return redirect()
            ->back()
            ->with('success', $user->first_name . ' ' . $user->last_name . ' has been unregistered from the tournament');
    }

    public function update(StoreOrUpdateTournamentRequest $request, Tournament $tournament): RedirectResponse
    {
        $this->authorize('update', [
            $tournament,
        ]);
        $validated = $request->validated();
        $tournament->update($validated);

        $tournament->rooms()->sync($validated['room_ids']);

        $this->tableService->linkAvailableTables($tournament);

        return redirect()
            ->route('tournamentsIndex')
            ->with('success', __('The tournament ' . $tournament->name . ' has been updated.'));
    }

    /**
     * Save match results
     */
    public function updateMatch(Request $request, TournamentMatch $match): RedirectResponse
    {

        $rules = [
            'sets' => 'required|array|min:3|max:5',
        ];

        // Ajouter des règles pour les 3 premiers sets (obligatoires)
        for ($i = 0; $i < 3; $i++) {
            $rules["sets.{$i}.player1_score"] = 'required|integer|min:0';
            $rules["sets.{$i}.player2_score"] = 'required|integer|min:0';
        }

        // Ajouter des règles pour les sets optionnels
        for ($i = 3; $i < 5; $i++) {
            $rules["sets.{$i}.player1_score"] = 'nullable|integer|min:0';
            $rules["sets.{$i}.player2_score"] = 'nullable|integer|min:0';
        }

        $validated = $request->validate($rules);

        // Vérifier qu'il y a au moins 3 sets avec des résultats valides
        $setsWithResults = array_filter($validated['sets'], function ($set) {
            return isset($set['player1_score']) && isset($set['player2_score']) &&
                ($set['player1_score'] > 0 || $set['player2_score'] > 0);
        });

        if (count($setsWithResults) < 3) {
            return redirect()->route('editMatch', $match)
                ->withInput()
                ->with('error', 'Au moins 3 sets doivent être joués.');
        }

        // Vérifier les règles de victoire pour chaque set
        foreach ($setsWithResults as $index => $set) {
            $player1Score = $set['player1_score'];
            $player2Score = $set['player2_score'];
            $maxScore = (int) max($player1Score, $player2Score);
            $minScore = (int) min($player1Score, $player2Score);

            // Vérifier que le gagnant a au moins 11 points
            if ($maxScore < 11) {
                return redirect()->route('editMatch', $match)
                    ->withInput()
                    ->with('error', 'Le gagnant doit avoir au moins 11 points.');
            }

            // Vérifier qu'il y a 2 points d'écart
            if ($maxScore - $minScore < 2) {
                return redirect()->route('editMatch', $match)
                    ->withInput()
                    ->with('error', 'Le set #' . ($index + 1) . ' doit avoir au moins 2 points d\'écart.');
            }

            // Vérifier qu'il y a 2 points d'écart
            if ($maxScore > 11 && $maxScore - $minScore !== 2) {
                return redirect()->route('editMatch', $match)
                    ->withInput()
                    ->with('error', 'Le set #' . ($index + 1) . ' doit avoir exactement 2 points d\'écart.');
            }

        }

        // Vérification du nombre de sets gagnants (3 sets gagnants)
        $player1SetsWon = 0;
        $player2SetsWon = 0;

        foreach ($setsWithResults as $set) {
            if ($set['player1_score'] > $set['player2_score']) {
                $player1SetsWon++;
            } else {
                $player2SetsWon++;
            }
        }

        // Vérifier qu'un joueur a bien gagné 3 sets
        $maxSetsWon = max($player1SetsWon, $player2SetsWon);
        if ($maxSetsWon < 3) {
            return redirect()->route('editMatch', $match)
                ->withInput()
                ->with('error', 'Un joueur doit gagner au minimum 3 sets.');
        }

        // Record results - ne garder que les sets avec des résultats
        $match->recordResult($setsWithResults);

        $this->tableService->freeUsedTable($match);

        // Editing a match from pool or from final bracket?
        if (isset($match->pool->tournament)) {
            return redirect()
                ->route('showPoolMatches', $match->pool)
                ->with('success', 'Résultats du match enregistrés avec succès');
        } else {

            if ($match->winner_id) {
                // Complete match and progress winner to next round
                $this->knockoutService->completeMatch($match, $match->winner_id);
            }

            return redirect()
                ->route('knockoutBracket', $match->tournament_id)
                ->with('success', 'Résultats du match enregistrés avec succès');
        }

    }

    /**
     * Met à jour la répartition des joueurs dans les poules.
     *
     * @return \Illuminate\Http\Response
     */
    public function updatePoolPlayers(Request $request, Tournament $tournament): RedirectResponse
    {
        // Vérifier l'autorisation
        $this->authorize('update', $tournament);

        // Récupérer les mouvements de joueurs depuis le formulaire
        $playerMoves = $request->input('player_moves', []);

        // Traiter chaque mouvement de joueur
        foreach ($playerMoves as $userId => $targetPoolId) {
            // Ne traiter que les entrées avec une valeur (pool cible)
            if (! empty($targetPoolId)) {
                // Vérifier que l'utilisateur et la pool existent
                $user = User::find($userId);
                $targetPool = Pool::find($targetPoolId);

                if ($user && $targetPool && $targetPool->tournament_id === $tournament->id) {
                    // Détacher l'utilisateur de toutes les poules de ce tournoi
                    foreach ($tournament->pools as $pool) {
                        $pool->users()->detach($userId);
                    }

                    // Attacher l'utilisateur à la nouvelle poule
                    $targetPool->users()->attach($userId);
                }
            }
        }

        return redirect()->back()->with('success', 'Répartition des joueurs mise à jour avec succès');
    }

    /**
     * Erase all matches from a pool.
     */
    private function eraseMatches(Pool $pool): void
    {
        $pool->tournamentmatches()->delete();
    }
}
