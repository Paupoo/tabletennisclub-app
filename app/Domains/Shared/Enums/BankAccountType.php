<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What a club bank account is for.
 *
 * Only a current account receives members' transfers: the automatic
 * reconciliation looks for payments there and nowhere else. A savings account
 * only ever moves money to and from the club's other accounts, or earns
 * interest.
 */
enum BankAccountType: string
{
    case Current = 'current';
    case Savings = 'savings';

    /**
     * Options for a select input.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type): array => ['id' => $type->value, 'name' => $type->label()],
            self::cases(),
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::Current => __('Current account'),
            self::Savings => __('Savings account'),
        };
    }
}
