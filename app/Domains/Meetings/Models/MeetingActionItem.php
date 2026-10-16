<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\Meetings\Models\MeetingActionItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int|null $agenda_item_id the point it was decided on; null: outside the agenda
 * @property string $title
 * @property string|null $description
 * @property int|null $assigned_to_id
 * @property Carbon|null $due_date
 * @property bool $is_completed
 * @property Carbon|null $completed_at when it was ticked done; null on those done before it was recorded
 * @property Carbon|null $abandoned_at dropped by the committee
 * @property string|null $abandon_reason
 * @property string|null $progress_note where it stands, in the owner's words
 * @property Carbon|null $progress_noted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $assignedTo
 * @property-read Meeting $meeting
 *
 * @method static \Database\Factories\Domains\Meetings\Models\MeetingActionItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereAssignedToId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereIsCompleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingActionItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[UseFactory(MeetingActionItemFactory::class)]
class MeetingActionItem extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'due_date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'abandoned_at' => 'datetime',
        'progress_noted_at' => 'datetime',
    ];

    protected $fillable = [
        'meeting_id',
        'agenda_item_id',
        'title',
        'description',
        'assigned_to_id',
        'due_date',
        'is_completed',
        'completed_at',
        'abandoned_at',
        'abandon_reason',
        'progress_note',
        'progress_noted_at',
    ];

    /** @return BelongsTo<MeetingAgendaItem, $this> */
    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function isOpen(): bool
    {
        return ! $this->is_completed && $this->abandoned_at === null;
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return HasMany<MeetingActionReview, $this> what each committee said of it */
    public function reviews(): HasMany
    {
        return $this->hasMany(MeetingActionReview::class, 'action_item_id');
    }

    /**
     * Neither done nor abandoned.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_completed', false)->whereNull('abandoned_at');
    }

    /**
     * Whoever ticks it — the owner on the reading page, the note taker, the
     * committee — the moment is recorded, so the next committee sees it once.
     */
    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (self $action): void {
            if ($action->isDirty('is_completed')) {
                $action->completed_at = $action->is_completed ? now() : null;
            }
        });
    }
}
