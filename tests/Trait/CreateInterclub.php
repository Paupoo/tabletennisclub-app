<?php

declare(strict_types=1);

namespace Tests\Trait;

trait CreateInterclub
{
    public function getValidInterclub(): array
    {
        return [
            'is_visited' => '1',
            'opposite_club_id' => '2',
            'opposite_team_name' => 'G',
            'room_id' => '1',
            'start_date_time' => now()->addDay()->format('Y-m-dTH:m'),
            'team_id' => '1',
        ];
    }

    public function getValidInterclubInTheClub(): array
    {
        return [
            'is_visited' => '1',
            'opposite_club_id' => '2',
            'opposite_team_name' => 'G',
            'room_id' => '1',
            'start_date_time' => now()->addDay()->format('Y-m-dTH:m'),
            'team_id' => '1',
        ];
    }

    public function getValidInterclubNotInTheClub(): array
    {
        return [
            'opposite_club_id' => '2',
            'opposite_team_name' => 'G',
            'room_id' => null,
            'start_date_time' => now()->addDay()->format('Y-m-dTH:m'),
            'team_id' => '1',
        ];
    }
}
