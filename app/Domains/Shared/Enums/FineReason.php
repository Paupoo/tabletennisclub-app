<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum FineReason: string
{
    case FORFEIT = 'forfeit';
    case LATE = 'late';
    case MISCONDUCT = 'misconduct';
    case OTHER = 'other';
    case UNJUSTIFIED_ABSENCE = 'unjustified_absence';

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function getOptions(): array
    {
        return array_map(
            fn (self $case): array => ['id' => $case->value, 'name' => $case->label()],
            self::cases()
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::FORFEIT => __('Forfeit'),
            self::LATE => __('Late arrival'),
            self::MISCONDUCT => __('Misconduct'),
            self::OTHER => __('Other'),
            self::UNJUSTIFIED_ABSENCE => __('Unjustified absence'),
        };
    }
}
