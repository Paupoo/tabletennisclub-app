<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        private TournamentTableService $tableService,
        private TournamentPoolService $poolService,
        private TournamentMatchService $matchService,
        private TournamentFinalPhaseService $knockoutService,
        ) {}

    public function index(): View
    {
        return view('admin.tournaments.index', [
            'rooms' => Room::orderBy('name')->get(),
            'tournament' => new Tournament(),
        ]);
    }

    public function show(string $id): View
    {
        $tournament = Tournament::findorFail($id);
        $this->tableService->linkAvailableTables($tournament);

        $unregisteredUsers = User::unregisteredUsers($tournament)->get();

        return view('admin.tournaments.show', [
            'tournament' => $tournament,
            'unregisteredUsers' => $unregisteredUsers,
        ]);
    }

    public function create(StoreOrUpdateTournamentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $tournament = Tournament::create($validated);

        foreach($request->room_ids as $room_id) {
            $tournament->rooms()->attach($room_id);
        }

        $this->tableService->linkAvailableTables($tournament);

        return redirect()
            ->route('tournamentsIndex')
            ->with('success', __('The tournament ' . $tournament->name . ' has been created.'));
    }

    public function update(Tournament $tournament, StoreOrUpdateTournamentRequest $request): RedirectResponse
    {

        $validated = $request->validated();
        $tournament->update($validated);
                
        $tournament->rooms()->sync($validated['room_ids']);

        $this->tableService->linkAvailableTables($tournament);


        return redirect()
            ->route('tournamentsIndex')
            ->with('success', __('The tournament ' . $tournament->name . ' has been created.'));
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        $tournament->delete();

        return redirect()
            ->route('tournamentsIndex')
            ->with('success', __('The tournament ' . $tournament->name . ' has been deleted.'));
    }

    public function changeStatus(Tournament $tournament, Request $request):RedirectResponse
    {
        $tournament->status = $request->status;
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Status for tournament ' . $tournament->name . ' has been updated.'));

    }

    public function unpublish(Tournament $tournament): RedirectResponse
    {
        $tournament->status = 'draft';
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been unpublished.'));
    }
    
    public function publish(Tournament $tournament): RedirectResponse
    {
        $tournament->status = 'open';
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been published.'));
    }

    public function start(Tournament $tournament): RedirectResponse
    {
        $tournament->status = 'pending';
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been started.'));
    }

    public function close(Tournament $tournament): RedirectResponse
    {
        $tournament->status = 'closed';
        $tournament->update();

        return redirect()
            ->back()
            ->with('success', __('Tournament ' . $tournament->name . ' has been closed.'));
    }

    public function registrerUser(Tournament $tournament, User $user): RedirectResponse
    {

        if ($this->IsFull($tournament)) {
            return redirect()
                ->route('tournamentShow', $tournament)
                ->with('error', 'Sorry, the tournament is full, you cannot register more players.');
        }

        $tournament->users()
            ->attach($user);
        $this->countRegisteredUsers($tournament);

        return redirect()
            ->route('tournamentShow', [$tournament])
            ->with('success', $user->first_name . ' ' . $user->last_name . ' has been registered to the tournament');
    }

    public function unregistrerUser(Tournament $tournament, User $user): RedirectResponse
    {
        $tournament->users()->detach($user);
        $this->countRegisteredUsers($tournament);

        return redirect()
            ->route('tournamentShow', [$tournament])
            ->with('success', $user->first_name . ' ' . $user->last_name . ' has been unregistered to the tournament');
    }

    /**
     * Add user to a pool
     *
     * @param Pool $pool
     * @param User $user
     * @return Exception|Redirect
     */
    public function addUserToPool(Pool $pool, User $user): Exception|RedirectResponse
    {
        // Vérifier que l'utilisateur participe au tournoi
        if (!$user->tournaments->contains($pool->tournament_id)) {
            throw new Exception('L\'utilisateur doit être inscrit au tournoi d\'abord');
        }

        // Utiliser notre méthode personnalisée qui applique la contrainte
        $pool->attachUser($user);

        return redirect()->back()->with('Utilisateur ajouté au pool avec succès');
    }

    public function countRegisteredUsers(Tournament $tournament): Int
    {
        $totalUsers = $tournament->users->count();
        $tournament->total_users = $totalUsers;
        $tournament->save();

        return $totalUsers;
    }

    public function setMaxPlayers(Tournament $tournament, Request $request): RedirectResponse
    {

        // Check that we don't already have more registered players
        if ($tournament->total_users > $request->max_users) {
            return redirect()
            ->route('tournamentSetup', $tournament)
            ->with([
                'error' => 'There are already ' . $tournament->total_users - $request->max_users. ' more players registered than the limit.',
            ]);
        }
        $tournament->max_users = $request->max_users;
        $tournament->save();

        return redirect()
            ->route('tournamentSetup', $tournament)
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
            ->route('tournamentSetup', $tournament)
            ->with([
                'success' => 'Start date updated successfully.',
            ]);
    }

    public function setEndTime(Tournament $tournament, Request $request): RedirectResponse
    {
        $end_date = Carbon::createFromFormat('Y-m-d\TH:i', $request->end_date, $request->timezone ?: config('app.timezone'));

        $tournament->end_date = $end_date;
        $tournament->save();

        return redirect()
            ->route('tournamentSetup', $tournament)
            ->with([
                'success' => 'End date updated successfully.',
            ]);
    }

    /**
     * Check if there the tournament has reached its maximum amount of players
     *
     * @param Tournament $tournament
     * @return boolean
     */
    private function IsFull(Tournament $tournament): bool
    {
        return ($tournament->total_users >= $tournament->max_users);
    }

    public function setUp(Tournament $tournament): View
    {
        return view('admin.tournaments.setup', [
            'tournament' => $tournament->load(['pools.users']),
            'rooms' => Room::orderBy('name')->get(),
        ]);
    }

    /**
     * Répartit les joueurs inscrits dans des pools
     */
    public function generatePools(Request $request, Tournament $tournament): RedirectResponse
    {
        $numberOfPools = intval($request->input('number_of_pools'));

        if($request->minMatches === 1) {
            $numberOfPools = intdiv($tournament->total_users, $numberOfPools+1);
        }
        // Utiliser le service pour distribuer les joueurs
        $pools = $this->poolService->distributePlayersInPools($tournament, $numberOfPools);

        return redirect()->back()->with([
            'success' => 'Joueurs répartis dans les poules avec succès',
            'pools' => $pools
        ]);
    }

    /**
     * Met à jour la répartition des joueurs dans les poules.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Tournament  $tournament
     * @return \Illuminate\Http\Response
     */
    public function updatePoolPlayers(Request $request, Tournament $tournament)
    {
        // Vérifier l'autorisation
        // $this->authorize('update', $tournament);

        // Récupérer les mouvements de joueurs depuis le formulaire
        $playerMoves = $request->input('player_moves', []);

        // Traiter chaque mouvement de joueur
        foreach ($playerMoves as $userId => $targetPoolId) {
            // Ne traiter que les entrées avec une valeur (pool cible)
            if (!empty($targetPoolId)) {
                // Vérifier que l'utilisateur et la pool existent
                $user = User::find($userId);
                $targetPool = Pool::find($targetPoolId);

                if ($user && $targetPool && $targetPool->tournament_id == $tournament->id) {
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

    public function toggleHasPaid(Tournament $tournament, User $user): RedirectResponse
    {
        $hasPaid = $tournament->users()->where('user_id', $user->id)->first()->pivot->has_paid;

        $tournament->users()->updateExistingPivot($user->id, [
            'has_paid' => !$hasPaid,
        ]);
        
        return redirect()
            ->route('tournamentShow', $tournament)
            ->with([
                'success' => 'Le paiement de ' . $user->first_name . ' ' . $user->last_name . ' a bien été ' . (($hasPaid) ? 'supprimé' : 'enregistré'),
            ]);

    }

    /**
     * Generate all matches for pools in a tournament
     * 
     * @param Tournament $tournament
     * @return RedirectResponse
     */
    public function generatePoolMatches(Tournament $tournament): RedirectResponse
    {
        $results = $this->matchService->generateTournamentMatches($tournament);
        
        $totalMatches = 0;
        foreach ($results as $poolMatches) {
            $totalMatches += $poolMatches->count();
        }
        
        return redirect()
            ->route('tournamentSetup', $tournament)
            ->with([
                'success' => $totalMatches . ' matches ont été générés avec succès pour toutes les poules',
            ]);
    }

    /**
     * Show the pool matches view
     * 
     * @param Pool $pool
     * @return View
     */
    public function showPoolMatches(Pool $pool): View
    {
        dump($this->poolService->isPoolFinished($pool));
        $matches = TournamentMatch::where('pool_id', $pool->id)
            ->orderBy('match_order')
            ->get();
        
        $standings = $this->matchService->calculatePoolStandings($pool);
        $tournament = $pool->tournament;
        $tables = $tournament->tables()
                        ->wherePivot('is_table_free', true)
                        ->orderBy('name')
                        ->get();
        
        return view('admin.tournaments.pool-matches', [
            'pool' => $pool,
            'tournament' => $tournament,
            'matches' => $matches,
            'standings' => $standings,
            'tables' => $tables,
        ]);
    }

    /**
     * Show match edit form
     * 
     * @param TournamentMatch $match
     * @return View
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
     * Save match results
     * 
     * @param Request $request
     * @param TournamentMatch $match
     * @return RedirectResponse
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
        $setsWithResults = array_filter($validated['sets'], function($set) {
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
            // dd($setsWithResults);
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
        
        // Editing a match from pool or from final bracket?
        if(isset($match->pool->tournament)){
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
     * Start match
     * 
     * @param TournamentMatch $match
     * @return RedirectResponse
     */
    public function startMatch(TournamentMatch $match, StartTournamentMatch $request): RedirectResponse
    {
        $table = Table::find($request->table_id);
        $tournament = $match->pool->tournament;
        $this->bookTableForMatch($table, $tournament, $match);
        $match->status = 'in_progress';
        $match->save();
        
        return redirect()
            ->route('showPoolMatches', $match->pool)
            ->with('success', 'Le match est en cours.');
    }

    public function bookTableForMatch(Table $table, Tournament $tournament, TournamentMatch $match): void
    {
        $relation = $table->tournaments()
        ->wherePivot('tournament_id', $tournament->id)
        ->wherePivotNull('tournament_match_id') // Vérifie que la table est libre
        // ->wherePivot('is_table_free', false)
        ->first();

        if (! $relation) {
            throw new \RuntimeException('Table déjà assignée à un match dans ce tournoi.');
        }
        
        $table->tournaments()
            ->updateExistingPivot($tournament->id, [
                'is_table_free' => false,
                'tournament_match_id' => $match->id,
                'match_started_at' => now(),
            ]);
    }

    /**
     * Reset match results
     * 
     * @param TournamentMatch $match
     * @return RedirectResponse
     */
    public function resetMatch(TournamentMatch $match): RedirectResponse
    {
        $match->sets()->delete();
        $match->winner_id = null;
        $match->status = 'scheduled';
        $match->save();
        
        return redirect()
            ->route('showPoolMatches', $match->pool)
            ->with('success', 'Résultats du match réinitialisés avec succès');
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
        
        foreach($tournament->pools() as $pool){
            // Pour chaque poule du tournoi, on récupère les meilleurs joueurs
            $sortedPlayers = $this->matchService->calculatePoolStandings($pool);
            
            for($i=0; $i<$TotalQualifiedPerPool; $i++){
                // On ajoute au tableau final les x premiers joueurs qualifiés
                $qualifiedPlayers[] = $sortedPlayers['players'][$i];
            }
        }

        // On obtient un tableau avec les joueurs triés par poule et par ordre dans la poule comme suit :A1, A2, B1, B2, C1, C2, D1, D2
        for($i=0; $i<$totalPools*$TotalQualifiedPerPool; $i++){
            // pour chaque joueur, on prend alternativement le premier et le dernier de la liste pour faire un match (A1-D2, A2-D1, B1-C2, B2-C1...)
            if($i % 2 === 0){
                $qualifiedPlayers[] = array_shift($sortedPlayers);
            } else {
                $qualifiedPlayers[] = array_pop($sortedPlayers);
            }
        }
        


        // si le nombre de joueur manque est supérieur à 0
        if($TotalPlayersToFill > 0){
            // On récupère tous les joueurs inscrits au tournoi, triés par nombres de matches remportés, puis de sets, puis de points
            $sortedPlayers = $this->matchService->calculateRowStandings($tournament);

            // On retire les joueurs déjà qualifié dans le tableau précédent
            $UnqualifiedPlayers = array_diff($sortedPlayers['players'], $sortedPlayers['players']);
            
            for($i=0; $i<$TotalPlayersToFill;$i++){
                // pour chaque joueur à repêcher, on prend le premier joueur et on l'ajoute au tableau de répêchage
                if($i % 2 === 0){
                    $qualifiedPlayers[] = array_shift($UnqualifiedPlayers);
                } else {
                    $qualifiedPlayers[] = array_pop($UnqualifiedPlayers);
                }
            }
        }

            
            // Pour chaque joueur repêché, on l'appaire avec un autre joueur pris au hasard
        
    }
}
