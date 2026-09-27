<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What a member spent the club's money on, as declared on an expense report.
 */
enum ExpenseCategory: string
{
    case Administrative = 'administrative';
    case Bar = 'bar';
    case Event = 'event';
    case Facilities = 'facilities';
    case Other = 'other';
    case SportsEquipment = 'sports_equipment';
    case Travel = 'travel';

    /**
     * In reading order, "Other" last.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function getOptions(): array
    {
        return array_map(
            fn (self $case): array => ['id' => $case->value, 'name' => $case->label()],
            [self::SportsEquipment, self::Facilities, self::Travel, self::Administrative, self::Event, self::Bar, self::Other],
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Administrative => __('Supplies / administration'),
            self::Bar => __('Purchase for the bar'),
            self::Event => __('Event / tournament'),
            self::Facilities => __('Hall equipment'),
            self::Other => __('Other'),
            self::SportsEquipment => __('Sports equipment'),
            self::Travel => __('Travel'),
        };
    }
}
