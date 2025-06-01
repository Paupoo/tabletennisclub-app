<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\MatchSet;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\TournamentFinalPhaseService;
use Illuminate\Http\Request;

class KnockoutPhaseController extends Controller
{
    protected $knockoutService;

    public function __construct(TournamentFinalPhaseService $knockoutService)
    {
        $this->knockoutService = $knockoutService;
    }

    /**
     * Show knockout phase setup page
     */
    public function setup(Tournament $tournament)
    {
        return view('admin.tournaments.knockout-setup', compact('tournament'));
    }

    /**
     * Configure knockout phase
     */
    public function configure(Request $request, Tournament $tournament)
    {
        $request->validate([
            'starting_round' => 'required|in:round_16,round_8,round_4',
        ]);

        $success = $this->knockoutService->configureKnockoutPhase(
            $tournament,
            $request->starting_round
        );

        if ($success) {
            return redirect()->route('knockoutBracket', $tournament)
                ->with('success', 'La phase finale a été configurée avec succès');
        }

        return back()->with('error', 'Erreur lors de la configuration de la phase finale');
    }

    /**
     * Show knockout bracket
     */
    public function showBracket(Tournament $tournament)
    {
        $rounds = $this->knockoutService->getKnockoutMatches($tournament);
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

        return view('admin.tournaments.knockout-bracket', compact('tournament', 'rounds', 'tables'));
    }

    /**
     * Start a match
     */
    public function startMatch(Request $request, TournamentMatch $match)
    {
        $request->validate([
            'tableNumber' => 'required|integer|min:1|max:8',
        ]);

        $match->update([
            'status' => 'in_progress',
            'table_number' => $request->tableNumber,
        ]);

        return redirect()->route('editMatch', $match);
    }

    /**
     * Update match
     */
    public function updateMatch(Request $request, TournamentMatch $match)
    {
        $request->validate([
            'sets' => 'required|array|min:1',
            'sets.*.player1_score' => 'required|integer|min:0',
            'sets.*.player2_score' => 'required|integer|min:0',
        ]);

        // Delete existing sets
        MatchSet::where('tournament_match_id', $match->id)->delete();

        $player1TotalSets = 0;
        $player2TotalSets = 0;

        // Create new sets
        foreach ($request->sets as $index => $setData) {
            $winner_id = null;

            if ($setData['player1_score'] > $setData['player2_score']) {
                $winner_id = $match->player1_id;
                $player1TotalSets++;
            } elseif ($setData['player2_score'] > $setData['player1_score']) {
                $winner_id = $match->player2_id;
                $player2TotalSets++;
            }

            MatchSet::create([
                'tournament_match_id' => $match->id,
                'set_number' => $index + 1,
                'player1_id' => $match->player1_id,
                'player2_id' => $match->player2_id,
                'player1_score' => $setData['player1_score'],
                'player2_score' => $setData['player2_score'],
                'winner_id' => $winner_id,
            ]);
        }

        // Determine match winner
        $winnerId = null;
        if ($player1TotalSets > $player2TotalSets) {
            $winnerId = $match->player1_id;
        } elseif ($player2TotalSets > $player1TotalSets) {
            $winnerId = $match->player2_id;
        }

        if ($winnerId) {
            // Complete match and progress winner to next round
            $this->knockoutService->completeMatch($match, $winnerId);
        }

        return redirect()->route('knockoutBracket', $match->tournament_id)
            ->with('success', 'Match mis à jour avec succès');
    }

    /**
     * Reset a match
     */
    public function resetMatch(TournamentMatch $match)
    {
        // Delete sets
        MatchSet::where('tournament_match_id', $match->id)->delete();

        // Reset match
        $match->update([
            'winner_id' => null,
            'status' => 'scheduled',
        ]);

        // If there's a next match with one of our players, reset it
        $this->knockoutService->cleanNextMatch($match);

        // If this is a semifinal, check if we need to update the bronze match
        if ($match->round === 'semifinal' && $match->bronze_match_id) {
            $bronzeMatch = TournamentMatch::find($match->bronze_match_id);

            if ($bronzeMatch) {
                if ($bronzeMatch->player1_id == $match->player1_id || $bronzeMatch->player1_id == $match->player2_id) {
                    $bronzeMatch->update(['player1_id' => null]);
                }

                if ($bronzeMatch->player2_id == $match->player1_id || $bronzeMatch->player2_id == $match->player2_id) {
                    $bronzeMatch->update(['player2_id' => null]);
                }
            }
        }

        return redirect()->route('knockoutBracket', $match->tournament_id)
            ->with('success', 'Match réinitialisé avec succès');
    }
}
