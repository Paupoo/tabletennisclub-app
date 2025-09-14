<?php
declare(strict_types=1);

namespace App\Enums;

enum EventType: string
{
    case CLUBLIFE = 'club_life';
    case INTERCLUB = 'interclub';
    case TOURNAMENT = 'tournament';
    case TRAINING = 'training';

    public function label(): string
    {
        return match ($this) {
            'club_life' => __('club_life'),
            'interclub' => __('interclub'),
            'tournamnet' => __('tournament'),
            'training' => __('training'),
        };
    }


    public function getIcon(): string
    {
        return match ($this) {
            'club-life' => '🎉',
            'interclub' => '🏓',
            'tournament' => '🏆',
            'training' => '🎯',
        };
    }
}
