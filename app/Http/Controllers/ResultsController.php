<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ResultsController extends Controller
{
    public function index(Request $request)
    {
        $selectedSeason = $request->get('season', '2024');
        $seasons = ['2025-2026', '2024-2025', '2023-2024'];

        $teams = [
            [
                'name' => 'Équipe A - Division 2C',
                'position' => '1ère Place',
                'position_class' => 'bg-green-100 text-green-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'Arc En Ciel F', 'venue' => 'Domicile', 'score' => '15-1', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 1, 'wins' => 1, 'losses' => 0, 'win_rate' => 100]
            ],
            [
                'name' => 'Équipe B - Division 3B',
                'position' => '5ème Place',
                'position_class' => 'bg-yellow-100 text-yellow-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'Arc En Ciel G', 'venue' => 'Domicile', 'score' => '8-8', 'result' => 'Nul'],
                ],
                'stats' => ['played' => 1, 'wins' => 0, 'losses' => 0, 'win_rate' => 0]
            ],
            [
                'name' => 'Équipe C - Division 4C',
                'position' => '2ème Place',
                'position_class' => 'bg-green-100 text-green-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'CTT Limal Wavre F', 'venue' => 'Extérieur', 'score' => '13-3', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 1, 'wins' => 1, 'losses' => 0, 'win_rate' => 100]
            ],
            [
                'name' => 'Équipe D - Division 4D',
                'position' => '1ère Place',
                'position_class' => 'bg-green-100 text-green-800',
                'matches' => [
                    ['date' => '13 Sep 2025', 'opponent' => 'CTT Le Moulin C', 'venue' => 'Extérieur', 'score' => '14-2', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 1, 'wins' => 1, 'losses' => 0, 'win_rate' => 100]
            ],
            [
                'name' => 'Équipe E - Division 5H',
                'position' => '5ème Place',
                'position_class' => 'bg-red-100 text-red-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'CTT Alpa Schaerbeek P', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Défaite'],
                ],
                'stats' => ['played' => 1, 'wins' => 0, 'losses' => 1, 'win_rate' => 0]
            ],
        ];

        // $teams = [];

        return view('public.results', compact('teams', 'seasons', 'selectedSeason'));
    }
}
