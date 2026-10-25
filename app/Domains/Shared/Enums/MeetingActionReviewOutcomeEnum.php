<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What a committee said of an earlier action while following it up.
 *
 * Done and abandoned close the action for good; the others leave it open, and
 * it comes back at the next committee. Not reviewed is written when the minutes
 * are published: the meeting ran out of time before reaching it.
 */
enum MeetingActionReviewOutcomeEnum: string
{
    case ABANDONED = 'abandoned';
    case DONE = 'done';
    case IN_PROGRESS = 'in_progress';
    case NOT_REVIEWED = 'not_reviewed';
    case POSTPONED = 'postponed';
    case REASSIGNED = 'reassigned';

    public function closesTheAction(): bool
    {
        return in_array($this, [self::DONE, self::ABANDONED], true);
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::DONE => 'badge-success badge-soft',
            self::ABANDONED => 'badge-ghost',
            self::IN_PROGRESS, self::POSTPONED, self::REASSIGNED => 'badge-info badge-soft',
            self::NOT_REVIEWED => 'badge-ghost',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ABANDONED => __('Abandoned'),
            self::DONE => __('Done'),
            self::IN_PROGRESS => __('In progress'),
            self::NOT_REVIEWED => __('Not reviewed'),
            self::POSTPONED => __('Postponed'),
            self::REASSIGNED => __('Reassigned'),
        };
    }
}
