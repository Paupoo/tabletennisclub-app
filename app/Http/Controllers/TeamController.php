<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    /**
     *  Define team functions
     */
    private function countCompetitors(): int
    {
        return User::where('is_competitor', '=', true)->count();
    }
    
    private function getCompetitors(): array
    {
        ;
    }

    public function proposeTeamsAmount(): array
    {

        $kerns = [7, 6, 5, 4];

        $resuts = [];

        foreach ($kerns as $kern) {
            $total_teams = round($this->countCompetitors() / $kern, 0);
            $remaining_players = $this->countCompetitors() % $kern;
            $results['Kern with ' . $kern . ' players'] = [
                'Total teams' => $total_teams,
                'Remaining players' => $remaining_players,
            ];
        }

        return $results;
    }

    private  function calculateTeamsAmount(int $kern_size): int
    {
        $total_teams = round($this->countCompetitors() / $kern_size, 0);

        return $total_teams;
    }

    public function proposeTeamsCompositions(Request $request)
    {
        $request->validate([
            'kern_size' => ['integer'],
        ]);
        // Store results
        $results = [];

        // Get competitors
        $competitors = User::where('is_competitor', '=', true)->orderby('force_index', 'asc')->orderby('last_name')->get();

        // Calculates team kerns size
        $kern = $request->kern_size; 
    

        $total_teams = $this->calculateTeamsAmount($kern);

        // For each team, get amount of kern players
        $letter = 'A';
        
        $start = 0;
        //Loop for each teach
        for ($i = 0; $i < $total_teams; $i++) {
            $players_count = 0;
            $results[$letter] = [];
            $players = array_slice($competitors->all(), $start,$kern);
            //Loop for each player
            foreach($players as $player) {
                $results[$letter]['player' . $players_count+1] = [];
                $results[$letter]['player' . $players_count+1] = ['name' => $player['last_name'], 'ranking' => $player['ranking']];

                $players_count++;
            }
            $start = $start + $kern;
            $letter++;
        }
        return $results;
    }
}
