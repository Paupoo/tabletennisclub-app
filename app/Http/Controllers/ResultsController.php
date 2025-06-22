<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function index(Request $request)
    {
        $selectedSeason = $request->get('season', '2024');
        $seasons = ['2024', '2023', '2022'];
        
        $teams = [
            [
                'name' => 'Équipe A - Division Premier',
                'position' => '2ème Place',
                'position_class' => 'bg-green-100 text-green-800',
                'matches' => [
                    ['date' => '15 Déc 2024', 'opponent' => 'Thunder TTC', 'venue' => 'Domicile', 'score' => '8-2', 'result' => 'Victoire'],
                    ['date' => '8 Déc 2024', 'opponent' => 'Elite Paddles', 'venue' => 'Extérieur', 'score' => '6-4', 'result' => 'Victoire'],
                    ['date' => '1 Déc 2024', 'opponent' => 'Spin Masters', 'venue' => 'Domicile', 'score' => '3-7', 'result' => 'Défaite'],
                    ['date' => '24 Nov 2024', 'opponent' => 'Rapid Rackets', 'venue' => 'Extérieur', 'score' => '7-3', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 12, 'wins' => 9, 'losses' => 3, 'win_rate' => 75]
            ],
            [
                'name' => 'Équipe B - Division 1',
                'position' => '1ère Place',
                'position_class' => 'bg-club-yellow text-club-blue',
                'matches' => [
                    ['date' => '14 Déc 2024', 'opponent' => 'City Spinners', 'venue' => 'Domicile', 'score' => '9-1', 'result' => 'Victoire'],
                    ['date' => '7 Déc 2024', 'opponent' => 'Paddle Power', 'venue' => 'Extérieur', 'score' => '8-2', 'result' => 'Victoire'],
                    ['date' => '30 Nov 2024', 'opponent' => 'Net Ninjas', 'venue' => 'Domicile', 'score' => '7-3', 'result' => 'Victoire'],
                    ['date' => '23 Nov 2024', 'opponent' => 'Smash Squad', 'venue' => 'Extérieur', 'score' => '6-4', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 10, 'wins' => 10, 'losses' => 0, 'win_rate' => 100]
            ]
        ];

        return view('results', compact('teams', 'seasons', 'selectedSeason'));
    }
}
