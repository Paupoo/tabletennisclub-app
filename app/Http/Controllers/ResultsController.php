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
                'position' => '2nde place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'Arc En Ciel F', 'venue' => 'Domicile', 'score' => '15-1', 'result' => 'Victoire'],
                    ['date' => '21 Sep 2025', 'opponent' => 'TT Zenith Brussels A', 'venue' => 'Extérieur', 'score' => '7-9', 'result' => 'Victoire'],
                    ['date' => '26 Sep 2025', 'opponent' => 'Braine l\'Alleud I', 'venue' => 'Domicile', 'score' => '14-2', 'result' => 'Victoire'],
                    ['date' => '10 Oct 2025', 'opponent' => 'Logis Auderghem I', 'venue' => 'Domicile', 'score' => '10-6', 'result' => 'Victoire'],
                    ['date' => '18 Oct 2025', 'opponent' => 'Gremlins A', 'venue' => 'Extérieur', 'score' => '3-13', 'result' => 'Victoire'],
                    ['date' => '7 Nov 2025', 'opponent' => 'Tourinnes A', 'venue' => 'Extérieur', 'score' => '9-7', 'result' => 'Défaite'],
                    ['date' => '14 Nov 2025', 'opponent' => 'La Hulpe-Rix. C', 'venue' => 'Extérieur', 'score' => '4-12', 'result' => 'Victoire'],
                    ['date' => '21 Nov 2025', 'opponent' => 'Set-Jet Fleur Bleue D', 'venue' => 'Domicile', 'score' => '13-3', 'result' => 'Victoire'],
                    ['date' => '28 Nov 2025', 'opponent' => 'Eveil B', 'venue' => 'Domicile', 'score' => '16-0', 'result' => 'Victoire'],
                    ['date' => '09 Jan 2026', 'opponent' => 'Arc En Ciel F', 'venue' => 'Extérieur', 'score' => '0-0', 'result' => 'Forfait Général'],
                    ['date' => '16 Jan 2026', 'opponent' => 'TT Zenith Brussels A', 'venue' => 'Domicile', 'score' => '11-5', 'result' => 'Victoire'],
                    ['date' => '23 Jan 2026', 'opponent' => 'Braine l\'Alleud I', 'venue' => 'Extérieur', 'score' => '0-16', 'result' => 'Forfait'],
                    ['date' => '06 Feb 2026', 'opponent' => 'Logis Auderghem I', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 11, 'wins' => 10, 'losses' => 1, 'win_rate' => 91]
            ],
            [
                'name' => 'Équipe B - Division 3B',
                'position' => '5ème place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'Arc En Ciel G', 'venue' => 'Domicile', 'score' => '8-8', 'result' => 'Nul'],
                    ['date' => '19 Sep 2025', 'opponent' => 'REP Nivelles D', 'venue' => 'Extérieur', 'score' => '7-9', 'result' => 'Victoire'],
                    ['date' => '26 Sep 2025', 'opponent' => 'Piranha G', 'venue' => 'Domicile', 'score' => '11-5', 'result' => 'Victoire'],
                    ['date' => '10 Oct 2025', 'opponent' => 'Royal 1865 B', 'venue' => 'Domicile', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => '18 Oct 2025', 'opponent' => 'TT Zenith Brussels B', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Victoire'],
                    ['date' => '7 Nov 2025', 'opponent' => 'Logis Auderghem N', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Victoire'],
                    ['date' => '14 Nov 2025', 'opponent' => 'La Hulpe Rix. D', 'venue' => 'Extérieur', 'score' => '11-5', 'result' => 'Défaite'],
                    ['date' => '21 Nov 2025', 'opponent' => 'Ry Ternel C', 'venue' => 'Domicile', 'score' => '5-11', 'result' => 'Défaite'],
                    ['date' => '28 Nov 2025', 'opponent' => 'Eveil D', 'venue' => 'Domicile', 'score' => '11-5', 'result' => 'Victoire'],
                    ['date' => '09 Jan 2026', 'opponent' => 'Arc En Ciel G', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Victoire'],
                    ['date' => '16 Jan 2026', 'opponent' => 'REP Nivelles D', 'venue' => 'Domicile', 'score' => '7-9', 'result' => 'Défaite'],
                    ['date' => '23 Jan 2026', 'opponent' => 'Piranha G', 'venue' => 'Extérieur', 'score' => '7-9', 'result' => 'Victoire'],
                    ['date' => '06 Feb 2026', 'opponent' => 'Royal 1865 B', 'venue' => 'Extérieur', 'score' => '13-3', 'result' => 'Défaite'],
                ],
                'stats' => ['played' => 13, 'wins' => 7, 'losses' => 3, 'win_rate' => 54]
            ],
            [
                'name' => 'Équipe C - Division 4C',
                'position' => '6ème place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'CTT Limal Wavre F', 'venue' => 'Extérieur', 'score' => '13-3', 'result' => 'Victoire'],
                    ['date' => '19 Sep 2025', 'opponent' => 'Safran A', 'venue' => 'Domicile', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => '27 Sep 2025', 'opponent' => 'Braine l\'Alleud M', 'venue' => 'Extérieur', 'score' => '3-13', 'result' => 'Victoire'],
                    ['date' => '10 Oct 2025', 'opponent' => 'Set-Jet Fleur Bleue K', 'venue' => 'Extérieur', 'score' => '1-15', 'result' => 'Victoire'],
                    ['date' => '18 Oct 2025', 'opponent' => 'Mont St Guibert B', 'venue' => 'Domicile', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => '7 Nov 2025', 'opponent' => 'Beauchamp A', 'venue' => 'Domicile', 'score' => '11-5', 'result' => 'Victoire'],
                    ['date' => '14 Nov 2025', 'opponent' => 'Witterzee A', 'venue' => 'Domicile', 'score' => '2-14', 'result' => 'Défaite'],
                    ['date' => '21 Nov 2025', 'opponent' => 'Set-Jet Fleur Bleue J', 'venue' => 'Extérieur', 'score' => '3-13', 'result' => 'Victoire'],
                    ['date' => '29 Nov 2025', 'opponent' => 'Logis Auderghem S', 'venue' => 'Extérieur', 'score' => '10-6', 'result' => 'Défaite'],
                    ['date' => '09 Jan 2026', 'opponent' => 'CTT Limal Wavre F', 'venue' => 'Dpmicile', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => '16 Jan 2026', 'opponent' => 'Safran A', 'venue' => 'Extérieur', 'score' => '8-8', 'result' => 'Nul'],
                    ['date' => '23 Jan 2026', 'opponent' => 'Braine l\'Alleud M', 'venue' => 'Domicile', 'score' => '7-9', 'result' => 'Défaite'],
                    ['date' => '06 Feb 2026', 'opponent' => 'Set-Jet Fleur Bleue K', 'venue' => 'Domicile', 'score' => '11-5', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 13, 'wins' => 6, 'losses' => 4, 'win_rate' => 46]
            ],
            [
                'name' => 'Équipe D - Division 4D',
                'position' => '6ème place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '13 Sep 2025', 'opponent' => 'CTT Le Moulin C', 'venue' => 'Extérieur', 'score' => '14-2', 'result' => 'Victoire'],
                    ['date' => '13 Sep 2025', 'opponent' => 'TT Zenith Brussels C', 'venue' => 'Domicile', 'score' => '1-15', 'result' => 'Défaite'],
                    ['date' => '27 Sep 2025', 'opponent' => 'Gremlins F', 'venue' => 'Extérieur', 'score' => '5-11', 'result' => 'Victoire'],
                    ['date' => '10 Oct 2025', 'opponent' => 'Piranha I', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Victoire'],
                    ['date' => '18 Oct 2025', 'opponent' => 'Eveil F', 'venue' => 'Domicile', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => '7 Nov 2025', 'opponent' => 'Arc En Ciel J', 'venue' => 'Domicile', 'score' => '5-11', 'result' => 'Défaite'],
                    ['date' => '14 Nov 2025', 'opponent' => 'Smash Evere C', 'venue' => 'Domicile', 'score' => '10-6', 'result' => 'Victoire'],
                    ['date' => '21 Nov 2025', 'opponent' => 'Set-Jet Fleur Bleue F', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => '28 Nov 2025', 'opponent' => 'Logis Auderghem Q', 'venue' => 'Extérieur', 'score' => '13-3', 'result' => 'Défaite'],
                    ['date' => '09 Jan 2026', 'opponent' => 'CTT Le Moulin C', 'venue' => 'Domicile', 'score' => '12-4', 'result' => 'Victoire'],
                    ['date' => '16 Jan 2026', 'opponent' => 'TT Zenith Brussels C', 'venue' => 'Extérieur', 'score' => '13-3', 'result' => 'Défaite'],
                    ['date' => '23 Jan 2026', 'opponent' => 'Gremlins F', 'venue' => 'Domicile', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => '06 Feb 2026', 'opponent' => 'Piranha I', 'venue' => 'Domicile', 'score' => '9-7', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 13, 'wins' => 6, 'losses' => 5, 'win_rate' => 46]
            ],
            [
                'name' => 'Équipe E - Division 5H',
                'position' => '7ème place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '12 Sep 2025', 'opponent' => 'CTT Alpa Schaerbeek P', 'venue' => 'Extérieur', 'score' => '6-10', 'result' => 'Défaite'],
                    ['date' => 'Bye', 'opponent' => 'Bye', 'venue' => 'Domicile', 'score' => 'Bye', 'result' => 'Bye'],
                    ['date' => '26 Sep 2025', 'opponent' => 'Tourinnes E', 'venue' => 'Extérieur', 'score' => '0-16', 'result' => 'Défaite'],
                    ['date' => '10 Oct 2025', 'opponent' => 'Braine l\'Alleud O', 'venue' => 'Extérieur', 'score' => '11-5', 'result' => 'Défaite'],
                    ['date' => '18 Oct 2025', 'opponent' => 'Logis Auderghem 2', 'venue' => 'Domicile', 'score' => '4-12', 'result' => 'Défaite'],
                    ['date' => '7 Nov 2025', 'opponent' => 'Limal Wavre I', 'venue' => 'Domicile', 'score' => '4-12', 'result' => 'Défaite'],
                    ['date' => '14 Nov 2025', 'opponent' => 'Royal Clabecq G', 'venue' => 'Domicile', 'score' => '4-12', 'result' => 'Défaite'],
                    ['date' => '21 Nov 2025', 'opponent' => 'REP Nivelles H', 'venue' => 'Extérieur', 'score' => '3-13', 'result' => 'Victoire'],
                    ['date' => 'Bye', 'opponent' => 'Bye', 'venue' => 'Domicile', 'score' => 'Bye', 'result' => 'Bye'],
                    ['date' => '09 Jan 2026', 'opponent' => 'CTT Alpa Schaerbeek P', 'venue' => 'Domicile', 'score' => '16-10', 'result' => 'Forfait'],
                    ['date' => 'Bye', 'opponent' => 'Bye', 'venue' => 'Domicile', 'score' => 'Bye', 'result' => 'Bye'],
                    ['date' => '23 Jan 2026', 'opponent' => 'Tourinnes E', 'venue' => 'Domicile', 'score' => '16-0', 'result' => 'Défaite'],
                    ['date' => '06 Feb 2026', 'opponent' => 'Braine l\'Alleud O', 'venue' => 'Domicile', 'score' => '4-12', 'result' => 'Défaite'],
                ],
                'stats' => ['played' => 9, 'wins' => 1, 'losses' => 6, 'win_rate' => 11]
            ],
            [
                'name' => 'Équipe A - Division 3B Vétérans',
                'position' => '3ème place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '03 Oct 2025', 'opponent' => 'Uccle Ping B', 'venue' => 'Domicile', 'score' => '10-0', 'result' => 'Victoire'],
                    ['date' => '31 Oct 2025', 'opponent' => 'Limal Wavre C', 'venue' => 'Domicile', 'score' => '7-3', 'result' => 'Victoire'],
                    ['date' => '05 Déc 2025', 'opponent' => 'Piranah C', 'venue' => 'Extérieur', 'score' => '5-5', 'result' => 'Nul'],
                    ['date' => '30 Jan 2026', 'opponent' => 'Fonteny Genappe A', 'venue' => 'Domicile', 'score' => '3-7', 'result' => 'Défaite'],
                ],
                'stats' => ['played' => 4, 'wins' => 2, 'losses' => 0, 'win_rate' => 50]
            ],
            [
                'name' => 'Équipe B - Division 3C Vétérans',
                'position' => '6ème place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '03 Oct 2025', 'opponent' => 'Logis Auderghem C', 'venue' => 'Extérieur', 'score' => '6-4', 'result' => 'Défaite'],
                    ['date' => '31 Oct 2025', 'opponent' => 'Arc En Ciel C', 'venue' => 'Domicile', 'score' => '3-7', 'result' => 'Défaite'],
                    ['date' => '05 Déc 2025', 'opponent' => 'TT Zenith Brussels A', 'venue' => 'Extérieur', 'score' => '5-5', 'result' => 'Nul'],
                    ['date' => '30 Jan 2026', 'opponent' => 'Set-Jet Fleur Bleue D', 'venue' => 'Domicile', 'score' => '7-3', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 4, 'wins' => 1, 'losses' => 2, 'win_rate' => 25]
            ],
            [
                'name' => 'Équipe C - Division 4F Vétérans',
                'position' => '2nde place',
                'position_class' => 'bg-gray-100 text-gray-800',
                'matches' => [
                    ['date' => '03 Oct 2025', 'opponent' => 'Logis Auderghem D', 'venue' => 'Domicile', 'score' => '7-3', 'result' => 'Victoire'],
                    ['date' => '31 Oct 2025', 'opponent' => 'Bye', 'venue' => 'Bye', 'score' => 'Bye', 'result' => 'Bye'],
                    ['date' => '05 Déc 2025', 'opponent' => 'Beauchamp B', 'venue' => 'Exétireur', 'score' => '1-9', 'result' => 'Victoire'],
                    ['date' => '30 Jan 2026', 'opponent' => 'Mont St Guibert B', 'venue' => 'Domicile', 'score' => '7-3', 'result' => 'Victoire'],
                ],
                'stats' => ['played' => 3, 'wins' => 3, 'losses' => 0, 'win_rate' => 100]
            ],
        ];

        return view('public.results', compact('teams', 'seasons', 'selectedSeason'));
    }
}
