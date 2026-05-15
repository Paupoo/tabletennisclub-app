<?php

declare(strict_types=1);

namespace App\Enums;

enum TournamentObjectiveEnum: string
{
    case Competitive = 'competitive';
    case Leisure = 'leisure';
    case MaximizeMatchesPerPlayer = 'maximize_matches_per_player';
    case MaximizePlayers = 'maximize_players';
    case MinimizeDuration = 'minimize_duration';

    /** @return array<int, array{id: string, name: string, description: string}> */
    public static function toOptions(): array
    {
        return array_map(
            fn (self $case) => [
                'id' => $case->value,
                'name' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases()
        );
    }

    public function description(): string
    {
        return match ($this) {
            self::MaximizePlayers => 'Fit as many players as possible in the available time.',
            self::MinimizeDuration => 'Complete the tournament as quickly as possible.',
            self::MaximizeMatchesPerPlayer => 'Ensure every player gets the most game time.',
            self::Leisure => 'Relaxed format with generous time per match.',
            self::Competitive => 'Standard competitive format with best-of-5 sets.',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MaximizePlayers => 'Maximize players',
            self::MinimizeDuration => 'Minimize duration',
            self::MaximizeMatchesPerPlayer => 'Maximize matches per player',
            self::Leisure => 'Leisure format',
            self::Competitive => 'Competitive format',
        };
    }
}
