<?php

declare(strict_types=1);

namespace Database\Seeders\Data;

use App\Domains\Shared\Enums\InterclubResultEnum;
use App\Domains\Shared\Enums\LeagueCategory;

/**
 * Single source of truth for the 2025-2026 interclub season.
 *
 * Consumed by:
 *  - InterclubScheduleSeeder → builds teams, divisions, opponents, Interclub fixtures (ignores score/result)
 *  - InterclubResultsSeeder  → applies score/result onto the InterclubResult rows
 *
 * Match shape:
 *   ['date' => 'Y-m-d', 'opponent' => 'Club Letter', 'home' => bool, 'score' => ?string, 'result' => ?InterclubResultEnum]
 *   ['date' => 'Y-m-d', 'bye' => true]   (a bye week — fixture exists, no opponent, no result)
 *
 * Matches with score === null are fixtures not yet played (e.g. the last week of 2026-04-17).
 */
class InterclubSeasonData
{
    public const SEASON_NAME = '2025-2026';

    public const SEASON_START = '2025-09-01 00:00:00';

    public const SEASON_END = '2026-06-30 00:00:00';

    /**
     * @return array<int, array{category: LeagueCategory, team: string, division: string, position: string, matches: array<int, array<string, mixed>>}>
     */
    public static function teams(): array
    {
        return [
            // ─────────────────────────── MEN ───────────────────────────
            [
                'category' => LeagueCategory::MEN,
                'team' => 'A',
                'division' => '2C',
                'position' => '1ère place',
                'matches' => [
                    ['date' => '2025-09-12', 'opponent' => 'Arc En Ciel F',         'home' => true,  'score' => '15-1', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-09-21', 'opponent' => 'TT Zenith Brussels A',  'home' => false, 'score' => '7-9',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-09-26', 'opponent' => "Braine l'Alleud I",     'home' => true,  'score' => '14-2', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-10', 'opponent' => 'Logis Auderghem I',     'home' => true,  'score' => '10-6', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-18', 'opponent' => 'Gremlins A',            'home' => false, 'score' => '3-13', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-07', 'opponent' => 'Tourinnes A',           'home' => false, 'score' => '9-7',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-14', 'opponent' => 'La Hulpe-Rix. C',       'home' => false, 'score' => '4-12', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-21', 'opponent' => 'Set-Jet Fleur Bleue D', 'home' => true,  'score' => '13-3', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-28', 'opponent' => 'Eveil B',               'home' => true,  'score' => '16-0', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-01-09', 'opponent' => 'Arc En Ciel F',         'home' => false, 'score' => null,   'result' => InterclubResultEnum::WITHDRAWAL_OPPONENT],
                    ['date' => '2026-01-16', 'opponent' => 'TT Zenith Brussels A',  'home' => true,  'score' => '11-5', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-01-23', 'opponent' => "Braine l'Alleud I",     'home' => false, 'score' => '0-16', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-02-06', 'opponent' => 'Logis Auderghem I',     'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-02-13', 'opponent' => 'Gremlins A',            'home' => true,  'score' => '16-0', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-03-13', 'opponent' => 'Tourinnes A',           'home' => true,  'score' => '12-4', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-03-27', 'opponent' => 'La Hulpe-Rix. C',       'home' => true,  'score' => '13-3', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-04-05', 'opponent' => 'Set-Jet Fleur Bleue D', 'home' => false, 'score' => '14-2', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-04-17', 'opponent' => 'Eveil B',               'home' => false, 'score' => null,   'result' => null],
                ],
            ],
            [
                'category' => LeagueCategory::MEN,
                'team' => 'B',
                'division' => '3B',
                'position' => '3ème place',
                'matches' => [
                    ['date' => '2025-09-12', 'opponent' => 'Arc En Ciel G',        'home' => true,  'score' => '8-8',  'result' => InterclubResultEnum::DRAW],
                    ['date' => '2025-09-19', 'opponent' => 'REP Nivelles D',        'home' => false, 'score' => '7-9',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-09-26', 'opponent' => 'Piranha G',             'home' => true,  'score' => '11-5', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-10', 'opponent' => 'Royal 1865 B',          'home' => true,  'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-10-18', 'opponent' => 'TT Zenith Brussels B',  'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-07', 'opponent' => 'Logis Auderghem N',     'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-14', 'opponent' => 'La Hulpe Rix. D',       'home' => false, 'score' => '11-5', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-21', 'opponent' => 'Ry Ternel C',           'home' => true,  'score' => '5-11', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-28', 'opponent' => 'Eveil D',               'home' => true,  'score' => '11-5', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-01-09', 'opponent' => 'Arc En Ciel G',         'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-01-16', 'opponent' => 'REP Nivelles D',        'home' => true,  'score' => '7-9',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-01-23', 'opponent' => 'Piranha G',             'home' => false, 'score' => '7-9',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-02-06', 'opponent' => 'Royal 1865 B',          'home' => false, 'score' => '13-3', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-02-13', 'opponent' => 'TT Zenith Brussels B',  'home' => true,  'score' => '12-4', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-03-13', 'opponent' => 'Logis Auderghem N',     'home' => true,  'score' => '7-9',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-27', 'opponent' => 'La Hulpe-Rix. D',       'home' => true,  'score' => '10-6', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-04-05', 'opponent' => 'Ry Ternel C',           'home' => false, 'score' => '16-0', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-04-17', 'opponent' => 'Eveil D',               'home' => false, 'score' => null,   'result' => null],
                ],
            ],
            [
                'category' => LeagueCategory::MEN,
                'team' => 'C',
                'division' => '4C',
                'position' => '5ème place',
                'matches' => [
                    ['date' => '2025-09-12', 'opponent' => 'CTT Limal Wavre F',     'home' => false, 'score' => '13-3', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-09-19', 'opponent' => 'Safran A',              'home' => true,  'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-09-27', 'opponent' => "Braine l'Alleud M",     'home' => false, 'score' => '3-13', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-10', 'opponent' => 'Set-Jet Fleur Bleue K', 'home' => false, 'score' => '1-15', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-18', 'opponent' => 'Mont St Guibert B',     'home' => true,  'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-07', 'opponent' => 'Beauchamp A',           'home' => true,  'score' => '11-5', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-14', 'opponent' => 'Witterzee A',           'home' => true,  'score' => '2-14', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-21', 'opponent' => 'Set-Jet Fleur Bleue J', 'home' => false, 'score' => '3-13', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-29', 'opponent' => 'Logis Auderghem S',     'home' => false, 'score' => '10-6', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-01-09', 'opponent' => 'CTT Limal Wavre F',     'home' => true,  'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-01-16', 'opponent' => 'Safran A',              'home' => false, 'score' => '8-8',  'result' => InterclubResultEnum::DRAW],
                    ['date' => '2026-01-23', 'opponent' => "Braine l'Alleud M",     'home' => true,  'score' => '7-9',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-02-06', 'opponent' => 'Set-Jet Fleur Bleue K', 'home' => true,  'score' => '11-5', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-02-13', 'opponent' => 'Mont St Guibert B',     'home' => false, 'score' => '10-6', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-03-13', 'opponent' => 'Beauchamp A',           'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-27', 'opponent' => 'Witterzee A',           'home' => false, 'score' => '0-16', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-04-05', 'opponent' => 'Set-Jet Fleur Bleue J', 'home' => true,  'score' => '15-1', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-04-17', 'opponent' => 'Logis Auderghem S',     'home' => true,  'score' => null,   'result' => null],
                ],
            ],
            [
                'category' => LeagueCategory::MEN,
                'team' => 'D',
                'division' => '4D',
                'position' => '7ème place',
                'matches' => [
                    ['date' => '2025-09-13', 'opponent' => 'CTT Le Moulin C',       'home' => false, 'score' => '14-2', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-09-13', 'opponent' => 'TT Zenith Brussels C',  'home' => true,  'score' => '1-15', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-09-27', 'opponent' => 'Gremlins F',            'home' => false, 'score' => '5-11', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-10', 'opponent' => 'Piranha I',             'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-18', 'opponent' => 'Eveil F',               'home' => true,  'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-07', 'opponent' => 'Arc En Ciel J',         'home' => true,  'score' => '5-11', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-14', 'opponent' => 'Smash Evere C',         'home' => true,  'score' => '10-6', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-21', 'opponent' => 'Set-Jet Fleur Bleue F', 'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-28', 'opponent' => 'Logis Auderghem Q',     'home' => false, 'score' => '13-3', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-01-09', 'opponent' => 'CTT Le Moulin C',       'home' => true,  'score' => '12-4', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-01-16', 'opponent' => 'TT Zenith Brussels C',  'home' => false, 'score' => '13-3', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-01-23', 'opponent' => 'Gremlins F',            'home' => true,  'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-02-06', 'opponent' => 'Piranha I',             'home' => true,  'score' => '9-7',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-02-13', 'opponent' => 'Eveil F',               'home' => false, 'score' => '4-12', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-13', 'opponent' => 'Arc En Ciel J',         'home' => false, 'score' => '3-13', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-27', 'opponent' => 'Smash Evere C',         'home' => false, 'score' => '5-11', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-04-05', 'opponent' => 'Set-Jet Fleur Bleue F', 'home' => true,  'score' => '9-7',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-04-17', 'opponent' => 'Logis Auderghem Q',     'home' => true,  'score' => null,   'result' => null],
                ],
            ],
            [
                'category' => LeagueCategory::MEN,
                'team' => 'E',
                'division' => '5H',
                'position' => '7ème place',
                'matches' => [
                    ['date' => '2025-09-12', 'opponent' => 'CTT Alpa Schaerbeek P', 'home' => false, 'score' => '6-10', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-09-19', 'bye' => true],
                    ['date' => '2025-09-26', 'opponent' => 'Tourinnes E',           'home' => false, 'score' => '0-16', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-10-10', 'opponent' => "Braine l'Alleud O",     'home' => false, 'score' => '11-5', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-10-18', 'opponent' => 'Logis Auderghem 2',     'home' => true,  'score' => '4-12', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-07', 'opponent' => 'Limal Wavre I',         'home' => true,  'score' => '4-12', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-14', 'opponent' => 'Royal Clabecq G',       'home' => true,  'score' => '4-12', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-11-21', 'opponent' => 'REP Nivelles H',        'home' => false, 'score' => '3-13', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-11-28', 'bye' => true],
                    ['date' => '2026-01-09', 'opponent' => 'CTT Alpa Schaerbeek P', 'home' => true,  'score' => '16-0', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-01-16', 'bye' => true],
                    ['date' => '2026-01-23', 'opponent' => 'Tourinnes E',           'home' => true,  'score' => '16-0', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-02-06', 'opponent' => "Braine l'Alleud O",     'home' => true,  'score' => '4-12', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-02-13', 'opponent' => 'Logis Auderghem 2',     'home' => false, 'score' => '3-13', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-13', 'opponent' => 'Limal Wavre I',         'home' => false, 'score' => '4-12', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-27', 'opponent' => 'Royal Clabecq G',       'home' => false, 'score' => '1-15', 'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-04-05', 'opponent' => 'REP Nivelles H',        'home' => true,  'score' => '7-9',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-04-17', 'bye' => true],
                ],
            ],

            // ───────────────────────── VETERANS ─────────────────────────
            [
                'category' => LeagueCategory::VETERANS,
                'team' => 'A',
                'division' => '3B',
                'position' => '3ème place',
                'matches' => [
                    ['date' => '2025-10-03', 'opponent' => 'Uccle Ping B',          'home' => true,  'score' => '10-0', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-31', 'opponent' => 'Limal Wavre C',         'home' => true,  'score' => '7-3',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-12-05', 'opponent' => 'Piranah C',             'home' => false, 'score' => '5-5',  'result' => InterclubResultEnum::DRAW],
                    ['date' => '2026-01-30', 'opponent' => 'Fonteny Genappe A',     'home' => true,  'score' => '3-7',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-02-20', 'opponent' => 'Set-Jet Fleur Bleue C', 'home' => false, 'score' => '4-6',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-03-06', 'opponent' => 'Gremlins B',            'home' => false, 'score' => '6-4',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-20', 'opponent' => 'REP Nivelles C',        'home' => false, 'score' => '0-10', 'result' => InterclubResultEnum::WIN],
                ],
            ],
            [
                'category' => LeagueCategory::VETERANS,
                'team' => 'B',
                'division' => '3C',
                'position' => '7ème place',
                'matches' => [
                    ['date' => '2025-10-03', 'opponent' => 'Logis Auderghem C',     'home' => false, 'score' => '6-4',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-10-31', 'opponent' => 'Arc En Ciel C',         'home' => true,  'score' => '3-7',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2025-12-05', 'opponent' => 'TT Zenith Brussels A',  'home' => false, 'score' => '5-5',  'result' => InterclubResultEnum::DRAW],
                    ['date' => '2026-01-30', 'opponent' => 'Set-Jet Fleur Bleue D', 'home' => true,  'score' => '7-3',  'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-02-20', 'opponent' => 'Palette Bleue A',       'home' => false, 'score' => '8-2',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-06', 'opponent' => 'Le Moulin A',           'home' => false, 'score' => '7-3',  'result' => InterclubResultEnum::LOSS],
                    ['date' => '2026-03-20', 'opponent' => 'Eveil C',               'home' => false, 'score' => '9-1',  'result' => InterclubResultEnum::LOSS],
                ],
            ],
            [
                'category' => LeagueCategory::VETERANS,
                'team' => 'C',
                'division' => '4F',
                'position' => '1ère place',
                'matches' => [
                    ['date' => '2025-10-03', 'opponent' => 'Logis Auderghem D',     'home' => true,  'score' => '7-3', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2025-10-31', 'bye' => true],
                    ['date' => '2025-12-05', 'opponent' => 'Beauchamp B',           'home' => false, 'score' => '1-9', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-01-30', 'opponent' => 'Mont St Guibert B',     'home' => true,  'score' => '7-3', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-02-20', 'opponent' => 'Gremlins D',            'home' => false, 'score' => '2-8', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-03-06', 'opponent' => 'Uccle Ping C',          'home' => false, 'score' => '3-7', 'result' => InterclubResultEnum::WIN],
                    ['date' => '2026-03-20', 'opponent' => 'Set-Jet Fleur Bleue I', 'home' => false, 'score' => '1-9', 'result' => InterclubResultEnum::WIN],
                ],
            ],
        ];
    }
}
