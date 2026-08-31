<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

enum TournamentStatusEnum: string
{
    case CANCELLED = 'cancelled';
    case CLOSED = 'closed';
    case DRAFT = 'draft';
    case LOCKED = 'locked';
    case PENDING = 'pending';
    case PUBLISHED = 'published';
    case SETUP = 'setup';

    /**
     * The statuses a filter can offer, in the order a tournament goes through
     * them rather than the alphabetical order the cases are declared in.
     *
     * @param  bool  $withDraft  Drafts are invisible to anybody who cannot manage tournaments.
     * @return array<int, array{id: string, name: string}>
     */
    public static function toOptions(bool $withDraft = false): array
    {
        $cases = [
            self::PENDING,
            self::PUBLISHED,
            self::SETUP,
            self::LOCKED,
            self::CLOSED,
            self::CANCELLED,
        ];

        if ($withDraft) {
            $cases[] = self::DRAFT;
        }

        return array_map(
            fn (self $case): array => ['id' => $case->value, 'name' => $case->getLabel()],
            $cases,
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
     * The daisyUI classes of the badge carrying this status.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'badge-outline',
            self::LOCKED => 'badge-warning badge-soft',
            self::PUBLISHED => 'badge-success badge-soft',
            self::SETUP => 'badge-info badge-soft',
            self::PENDING => 'badge-primary badge-soft',
            self::CLOSED => 'badge-ghost',
            self::CANCELLED => 'badge-error badge-soft',
        };
    }

    /**
     * The human-readable label of this status.
     *
     * It used to be recopied in three views, with three different wordings for
     * `published`, `setup` and `locked`: somebody filtering on "Registrations
     * open" got rows badged "Published". The filter and the column read the
     * same sentence from here now.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => __('Draft'),
            self::LOCKED => __('Ready to open'),
            self::PUBLISHED => __('Registrations open'),
            self::SETUP => __('Registrations closed'),
            self::PENDING => __('Live'),
            self::CLOSED => __('Closed'),
            self::CANCELLED => __('Cancelled'),
        };
    }
}
