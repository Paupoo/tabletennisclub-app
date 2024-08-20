<?php

namespace Tests\Unit;

use App\Enums\LeagueCategory;
use App\Models\Interclub;
use PHPUnit\Framework\TestCase;

class InterclubTest extends TestCase
{

    public function test_method_set_total_players_per_team_without_empty_string(): void
    {
        $interclub = new Interclub();

        $this->expectException('Exception');
        $this->expectExceptionMessage('This category is unknown and not allowed.');
        $interclub->setTotalPlayersPerteam('');
    }

    public function test_method_set_total_players_per_team_with_invalid_string(): void
    {
        $interclub = new Interclub();

        $this->expectException('Exception');
        $this->expectExceptionMessage('This category is unknown and not allowed.');

        $interclub->setTotalPlayersPerteam('Homme');
    }

    public function test_method_set_total_players_per_team_positive_tests(): void
    {
        $interclub = new Interclub();
        
        $interclub->setTotalPlayersPerteam(LeagueCategory::MEN->name);
        $this->assertEquals(4, $interclub->total_players);

        $interclub->setTotalPlayersPerteam(LeagueCategory::WOMEN->name);
        $this->assertEquals(3, $interclub->total_players);
    
        $interclub->setTotalPlayersPerteam(LeagueCategory::VETERANS->name);    
        $this->assertEquals(3, $interclub->total_players);
    }
    
    public function test_method_set_week_number_wrong_date(): void
    {
        $interclub = new Interclub();

        $this->expectException('Exception');
        $interclub->setWeekNumber('202E-08-17');
    }

    public function test_method_set_week_number_positive_tests(): void
    {
        $interclub = new Interclub();

        $interclub->setWeekNumber('2024-08-21');
        $this->assertEquals(34, $interclub->week_number);

        $interclub->setWeekNumber('2025-03-23');
        $this->assertEquals(12, $interclub->week_number);
    }
}
