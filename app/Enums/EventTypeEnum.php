<?php

declare(strict_types=1);

namespace App\Enums;

enum EventTypeEnum: string
{
    // case COMMUNITY_EVENT = 'COMMUNITY_EVENT';
    case INTERCLUB = 'INTERCLUB';
    // case MEETING = 'MEETING';
    case TOURNAMENT = 'TOURNAMENT';
    case TRAINING = 'TRAINING';

    /**
     * Return the values of the enum into an array
     * @return array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Retur the localized string of a particular value
     * @return array|string|null
     */
    public function getLabel(): string
    {
        return match ($this) {
            // self::COMMUNITY_EVENT => __('Community event'),
            self::INTERCLUB => __('Interclub'),
            // self::MEETING => __('Meeting'),
            self::TOURNAMENT => __('Tournament'),
            self::TRAINING => __('Training'),
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            // self::COMMUNITY_EVENT => '🎉',
            self::INTERCLUB => '🏓',
            self::TOURNAMENT => '🏆',
            self::TRAINING => '🎯',
        };
    }
}
