<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Where a member stands with the club, read off their affiliations across
 * seasons and the departure they may have declared — never stored.
 *
 * "Affiliated in a season" means a subscription of that season still under way
 * (pending, confirmed or paid): the same rule as
 * `User::scopeAffiliatedForCurrentSeason()`. A cancelled or refunded one counts
 * for nothing, so a member who cancelled is read as if they had never asked.
 *
 * The rule lives twice, and on purpose: once in PHP ({@see self::fromAffiliations()})
 * for the row, once in SQL (`User::scopeInMembershipStatus()`) for the filter.
 * Both read the same four facts — left this season, affiliated this season,
 * last season, before this season — and a test holds them to the same answer
 * on every case.
 *
 * A departure declared for the running season outweighs everything else: the
 * member may well still be affiliated, they are gone all the same. One declared
 * for an earlier season says nothing any more.
 */
enum MembershipStatus: string
{
    case Former = 'former';
    case Left = 'left';
    case Never = 'never';
    case New = 'new';
    case Renewed = 'renewed';
    case Returning = 'returning';
    case ToFollowUp = 'to_follow_up';

    /**
     * The members the list opens on: everyone the club counts this season, and
     * those of last season it still has to hear from.
     *
     * @return list<self>
     */
    public static function currentMembers(): array
    {
        return [self::New, self::Renewed, self::Returning, self::ToFollowUp];
    }

    /**
     * @param  bool  $thisSeason  affiliated in the running season
     * @param  bool  $lastSeason  affiliated in the season before it
     * @param  bool  $beforeThisSeason  affiliated in any season before the running one, the last included
     * @param  bool  $leftThisSeason  declared their departure for the running season
     */
    public static function fromAffiliations(bool $thisSeason, bool $lastSeason, bool $beforeThisSeason, bool $leftThisSeason = false): self
    {
        return match (true) {
            $leftThisSeason => self::Left,
            $thisSeason && ! $beforeThisSeason => self::New,
            $thisSeason && $lastSeason => self::Renewed,
            $thisSeason => self::Returning,
            $lastSeason => self::ToFollowUp,
            $beforeThisSeason => self::Former,
            default => self::Never,
        };
    }

    /**
     * The order a member travels through them, the closest to the club first.
     * Not `cases()`, which Pint keeps in alphabetical order.
     *
     * @return list<self>
     */
    public static function inReadingOrder(): array
    {
        return [self::New, self::Renewed, self::Returning, self::ToFollowUp, self::Left, self::Former, self::Never];
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

    /**
     * Soft variants only: a solid status badge fails AA against its own
     * `-content` colour (DS-B).
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::New => 'badge-info badge-soft',
            self::Renewed => 'badge-success badge-soft',
            self::Returning => 'badge-primary badge-soft',
            self::ToFollowUp => 'badge-warning badge-soft',
            self::Left => 'badge-error badge-soft',
            self::Former, self::Never => 'badge-ghost',
        };
    }

    public function isAffiliatedThisSeason(): bool
    {
        return in_array($this, [self::New, self::Renewed, self::Returning], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::New => __('New'),
            self::Renewed => __('Renewed'),
            self::Returning => __('Returning'),
            self::ToFollowUp => __('To follow up'),
            self::Left => __('Left the club'),
            self::Former => __('Former member'),
            self::Never => __('Never affiliated'),
        };
    }
}
