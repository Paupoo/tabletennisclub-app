<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What the club's money was spent on: the nine expense categories the
 * accounts are presented in.
 *
 * One vocabulary for every expense, whether a member declared it on an
 * expense report or the treasurer filed the invoice as a supporting document,
 * so the financial report adds them up without a translation table.
 */
enum ExpenseCategory: string
{
    case Bar = 'bar';
    case Event = 'event';
    case Federation = 'federation';
    case Hall = 'hall';
    case Operations = 'operations';
    case Other = 'other';
    case SportsEquipment = 'sports_equipment';
    case Training = 'training';
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
            [self::Federation, self::Hall, self::SportsEquipment, self::Training, self::Event, self::Bar, self::Operations, self::Travel, self::Other],
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Bar => __('Bar'),
            self::Event => __('Events & tournaments'),
            self::Federation => __('Federation & competitions'),
            self::Hall => __('Hall'),
            self::Operations => __('Running costs'),
            self::Other => __('Other expenses'),
            self::SportsEquipment => __('Sports equipment'),
            self::Training => __('Training & education'),
            self::Travel => __('Travel expenses'),
        };
    }
}
