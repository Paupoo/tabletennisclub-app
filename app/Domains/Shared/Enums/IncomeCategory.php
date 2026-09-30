<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Where the club's money came from: the income categories the accounts are
 * presented in, next to the nine {@see ExpenseCategory}.
 *
 * Two of them are only ever fed by the website — membership fees and
 * trainings, read off the affiliations' payments — and a supporting document
 * may not claim them: the same euro would be counted twice.
 *
 * The values never collide with an expense category's, so a category can be
 * told from its value alone.
 */
enum IncomeCategory: string
{
    case Bar = 'bar_income';
    case Event = 'event_income';
    case MembershipFees = 'membership_fees';
    case Other = 'other_income';
    case Sponsorship = 'sponsorship';
    case Subsidies = 'subsidies';
    case Trainings = 'trainings';

    /**
     * What a supporting document may be filed under: every income the website
     * does not already account for.
     *
     * @return list<self>
     */
    public static function forDocuments(): array
    {
        return array_values(array_filter(self::ordered(), fn (self $case): bool => ! $case->isSiteOnly()));
    }

    /**
     * In reading order, "Other" last.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::MembershipFees, self::Trainings, self::Event, self::Bar, self::Subsidies, self::Sponsorship, self::Other];
    }

    /**
     * Only the website's payments feed it.
     */
    public function isSiteOnly(): bool
    {
        return in_array($this, [self::MembershipFees, self::Trainings], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Bar => __('Bar'),
            self::Event => __('Events & tournaments'),
            self::MembershipFees => __('Membership fees'),
            self::Other => __('Other income'),
            self::Sponsorship => __('Sponsorship & donations'),
            self::Subsidies => __('Subsidies'),
            self::Trainings => __('Trainings'),
        };
    }
}
