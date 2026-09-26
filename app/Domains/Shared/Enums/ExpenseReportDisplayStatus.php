<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * The status a member or a treasurer reads: the stored one, with "paid" taking
 * over "accepted" once the refund has left the club's account.
 */
enum ExpenseReportDisplayStatus: string
{
    case Accepted = 'accepted';
    case Paid = 'paid';
    case Rejected = 'rejected';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public static function getOptions(): array
    {
        return array_map(
            fn (self $case): array => ['id' => $case->value, 'name' => $case->label()],
            [self::Submitted, self::Accepted, self::Paid, self::Rejected, self::Withdrawn],
        );
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Accepted => 'badge-info badge-soft',
            self::Paid => 'badge-success badge-soft',
            self::Rejected => 'badge-error badge-soft',
            self::Submitted => 'badge-warning badge-soft',
            self::Withdrawn => 'badge-ghost',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Accepted => __('Accepted'),
            self::Paid => __('Paid'),
            self::Rejected => __('Rejected'),
            self::Submitted => __('In progress'),
            self::Withdrawn => __('Withdrawn'),
        };
    }
}
