<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MeetingActionReviewOutcomeEnum;
use Database\Factories\Domains\Meetings\Models\MeetingActionReviewFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One line of a committee's follow-up: what it said of an earlier action.
 *
 * Frozen on purpose. The action goes on living — ticked done a month later,
 * handed to someone else — while this line keeps what the minutes recorded
 * that evening: the outcome, the new due date or owner, the owner's note.
 *
 * @property int $id
 * @property int $meeting_id the committee that followed the action up
 * @property int $action_item_id
 * @property MeetingActionReviewOutcomeEnum $outcome
 * @property Carbon|null $due_date the new due date, when postponed
 * @property int|null $assigned_to_id the new owner, when reassigned
 * @property string|null $reason why it was abandoned
 * @property string|null $progress_note the owner's note, as it read then
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MeetingActionItem $actionItem
 * @property-read User|null $assignedTo
 * @property-read Meeting $meeting
 */
#[UseFactory(MeetingActionReviewFactory::class)]
class MeetingActionReview extends Model
{
    /** @use HasFactory<MeetingActionReviewFactory> */
    use HasFactory;

    protected $casts = [
        'outcome' => MeetingActionReviewOutcomeEnum::class,
        'due_date' => 'date',
    ];

    protected $fillable = [
        'meeting_id',
        'action_item_id',
        'outcome',
        'due_date',
        'assigned_to_id',
        'reason',
        'progress_note',
    ];

    /** @return BelongsTo<MeetingActionItem, $this> */
    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(MeetingActionItem::class, 'action_item_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** "Postponed to 30/11/2026", "Handed over to Jean Appel", "Abandoned — the room closed". */
    public function describe(): string
    {
        return match ($this->outcome) {
            MeetingActionReviewOutcomeEnum::POSTPONED => $this->due_date
                ? __('Postponed to :date', ['date' => $this->due_date->format('d/m/Y')])
                : $this->outcome->getLabel(),
            MeetingActionReviewOutcomeEnum::REASSIGNED => $this->assignedTo
                ? __('Handed over to :name', ['name' => $this->assignedTo->full_name])
                : $this->outcome->getLabel(),
            MeetingActionReviewOutcomeEnum::ABANDONED => filled($this->reason)
                ? __('Abandoned — :reason', ['reason' => $this->reason])
                : $this->outcome->getLabel(),
            default => $this->outcome->getLabel(),
        };
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
