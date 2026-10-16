<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Services;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingActionReview;
use App\Domains\Shared\Enums\MeetingActionReviewOutcomeEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The actions a committee follows up, and what it says of each.
 *
 * Every committee opens on the actions handed out at earlier meetings — its
 * own and the general assemblies' — that are still open, plus those closed
 * since and never confirmed by a committee: ticked done by their owner between
 * two meetings, they come back once so the committee sees them go.
 *
 * Until the minutes are published the list is live. Publishing freezes it:
 * what the meeting never reached is written down as not reviewed, what was
 * ticked done meanwhile as done.
 */
final class ActionFollowUp
{
    /**
     * Whether this meeting follows actions up at all: committees do, a general
     * assembly does not — the committee carries out what the assembly asked.
     */
    public static function appliesTo(Meeting $meeting): bool
    {
        return $meeting->type === MeetingTypeEnum::COMMITTEE && $meeting->scheduled_at !== null;
    }

    /**
     * Freeze the follow-up when the minutes are published.
     */
    public static function freeze(Meeting $meeting): void
    {
        if (! self::appliesTo($meeting)) {
            return;
        }

        DB::transaction(function () use ($meeting): void {
            foreach (self::unreviewed($meeting) as $action) {
                MeetingActionReview::query()->create([
                    'meeting_id' => $meeting->id,
                    'action_item_id' => $action->id,
                    'outcome' => match (true) {
                        $action->is_completed => MeetingActionReviewOutcomeEnum::DONE,
                        $action->abandoned_at !== null => MeetingActionReviewOutcomeEnum::ABANDONED,
                        default => MeetingActionReviewOutcomeEnum::NOT_REVIEWED,
                    },
                    'reason' => $action->abandon_reason,
                    'progress_note' => $action->progress_note,
                ]);
            }
        });
    }

    /**
     * The actions this committee follows up: still open, or closed since and
     * never confirmed — plus, once reviewed here, whatever it said of them.
     *
     * @return Collection<int, MeetingActionItem>
     */
    public static function itemsFor(Meeting $meeting): Collection
    {
        if (! self::appliesTo($meeting)) {
            return new Collection;
        }

        return self::candidates($meeting)
            ->where(fn (Builder $query) => $query
                ->whereHas('reviews', fn (Builder $review) => $review->where('meeting_id', $meeting->id))
                ->orWhere(fn (Builder $pending) => self::pending($pending, $meeting)))
            ->with(['assignedTo', 'meeting', 'reviews' => fn ($reviews) => $reviews->where('meeting_id', $meeting->id)])
            ->get()
            ->sortBy([
                fn (MeetingActionItem $a, MeetingActionItem $b): int => ($a->due_date?->timestamp ?? PHP_INT_MAX) <=> ($b->due_date?->timestamp ?? PHP_INT_MAX),
                fn (MeetingActionItem $a, MeetingActionItem $b): int => $a->id <=> $b->id,
            ])
            ->values();
    }

    /**
     * Open actions waiting for the next committee, the way the convocation and
     * the owner see them: the meeting's own reviews do not matter yet.
     *
     * @return Collection<int, MeetingActionItem>
     */
    public static function openBefore(Meeting $meeting, ?User $owner = null): Collection
    {
        if (! self::appliesTo($meeting)) {
            return new Collection;
        }

        return self::candidates($meeting)
            ->open()
            ->when($owner instanceof User, fn (Builder $query) => $query->where('assigned_to_id', $owner?->id))
            ->with(['assignedTo', 'meeting'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('meeting_action_items.id')
            ->get();
    }

    /**
     * Record what the committee says of one action, and carry it onto the
     * action itself. Saying it again replaces the line: the minutes keep the
     * last word of the evening.
     *
     * @param  array{due_date?: Carbon|null, assigned_to_id?: int|null, reason?: string|null}  $details
     */
    public static function review(Meeting $meeting, MeetingActionItem $action, MeetingActionReviewOutcomeEnum $outcome, array $details = []): MeetingActionReview
    {
        abort_unless(self::appliesTo($meeting), 403);
        abort_unless(self::itemsFor($meeting)->contains('id', $action->id), 404);

        return DB::transaction(function () use ($meeting, $action, $outcome, $details): MeetingActionReview {
            match ($outcome) {
                MeetingActionReviewOutcomeEnum::DONE => $action->update(['is_completed' => true, 'abandoned_at' => null, 'abandon_reason' => null]),
                MeetingActionReviewOutcomeEnum::ABANDONED => $action->update(['is_completed' => false, 'abandoned_at' => now(), 'abandon_reason' => $details['reason'] ?? null]),
                MeetingActionReviewOutcomeEnum::POSTPONED => $action->update(['is_completed' => false, 'abandoned_at' => null, 'abandon_reason' => null, 'due_date' => $details['due_date'] ?? null]),
                MeetingActionReviewOutcomeEnum::REASSIGNED => $action->update(['is_completed' => false, 'abandoned_at' => null, 'abandon_reason' => null, 'assigned_to_id' => $details['assigned_to_id'] ?? null]),
                MeetingActionReviewOutcomeEnum::IN_PROGRESS, MeetingActionReviewOutcomeEnum::NOT_REVIEWED => $action->update(['is_completed' => false, 'abandoned_at' => null, 'abandon_reason' => null]),
            };

            return MeetingActionReview::query()->updateOrCreate(
                ['meeting_id' => $meeting->id, 'action_item_id' => $action->id],
                [
                    'outcome' => $outcome,
                    'due_date' => $outcome === MeetingActionReviewOutcomeEnum::POSTPONED ? ($details['due_date'] ?? null) : null,
                    'assigned_to_id' => $outcome === MeetingActionReviewOutcomeEnum::REASSIGNED ? ($details['assigned_to_id'] ?? null) : null,
                    'reason' => $outcome === MeetingActionReviewOutcomeEnum::ABANDONED ? ($details['reason'] ?? null) : null,
                    'progress_note' => $action->progress_note,
                ],
            );
        });
    }

    /**
     * Pending and not yet said anything of at this meeting.
     *
     * @return Collection<int, MeetingActionItem>
     */
    public static function unreviewed(Meeting $meeting): Collection
    {
        return self::itemsFor($meeting)->filter(fn (MeetingActionItem $action): bool => $action->reviews->isEmpty())->values();
    }

    /** @return Builder<MeetingActionItem> actions handed out at a meeting held before this one */
    private static function candidates(Meeting $meeting): Builder
    {
        return MeetingActionItem::query()
            ->where('meeting_id', '!=', $meeting->id)
            ->whereHas('meeting', fn (Builder $origin) => $origin->where('scheduled_at', '<', $meeting->scheduled_at));
    }

    /**
     * Open, or closed since and never confirmed by a committee. Closed before
     * the moment was recorded (`completed_at` null) means long settled.
     *
     * @param  Builder<MeetingActionItem>  $query
     */
    private static function pending(Builder $query, Meeting $meeting): void
    {
        $query
            ->where(fn (Builder $state) => $state
                ->where(fn (Builder $open) => $open->where('is_completed', false)->whereNull('abandoned_at'))
                ->orWhere(fn (Builder $done) => $done->where('is_completed', true)->whereNotNull('completed_at'))
                ->orWhereNotNull('abandoned_at'))
            ->whereDoesntHave('reviews', fn (Builder $review) => $review
                ->where('meeting_id', '!=', $meeting->id)
                ->whereIn('outcome', [MeetingActionReviewOutcomeEnum::DONE, MeetingActionReviewOutcomeEnum::ABANDONED]));
    }
}
