<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Why a member left the club, as the office heard it.
 *
 * Coarse on purpose: the point is to count the departures the club could have
 * done something about (cost, time) apart from those it could not (moving,
 * health). Whatever does not fit goes to "other" and the free note.
 */
enum DepartureReason: string
{
    case Cost = 'cost';
    case Health = 'health';
    case Moving = 'moving';
    case NoResponse = 'no_response';
    case Other = 'other';
    case Time = 'time';
    case Transfer = 'transfer';

    /**
     * The order the drawer lists them in. Not `cases()`, which Pint keeps in
     * alphabetical order — "other" belongs last.
     *
     * @return list<self>
     */
    public static function inReadingOrder(): array
    {
        return [self::Moving, self::Health, self::Time, self::Transfer, self::Cost, self::NoResponse, self::Other];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['id' => $case->value, 'name' => $case->label()],
            self::inReadingOrder(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Moving => __('Moving away'),
            self::Health => __('Health / injury'),
            self::Time => __('Lack of time'),
            self::Transfer => __('Transfer to another club'),
            self::Cost => __('Cost'),
            self::NoResponse => __('No news'),
            self::Other => __('Other'),
        };
    }
}
