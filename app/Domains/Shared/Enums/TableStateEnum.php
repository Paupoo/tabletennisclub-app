<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum TableStateEnum: string
{
    case GOOD = 'good';
    case NEEDS_REPAIR = 'needs_repair';
    case OUT_OF_SERVICE = 'out_of_service';

    /**
     * Options for a select input.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $state): array => ['id' => $state->value, 'name' => $state->getLabel()],
            self::cases()
        );
    }

    /**
     * Return the values of the enum into an array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return the localized string of a particular value
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::GOOD => __('Good condition'),
            self::NEEDS_REPAIR => __('Needs repair'),
            self::OUT_OF_SERVICE => __('Out of service'),
        };
    }

    /**
     * Whether a table in this state can be used for play.
     *
     * "Needs repair" flags upcoming maintenance, not a table pulled from play.
     */
    public function isPlayable(): bool
    {
        return $this !== self::OUT_OF_SERVICE;
    }
}
