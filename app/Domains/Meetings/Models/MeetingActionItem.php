<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\Meetings\Models\MeetingActionItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property string $title
 * @property string|null $description
 * @property int|null $assigned_to_id
 * @property Carbon|null $due_date
 * @property bool $is_completed
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
    ];

    protected $fillable = [
        'meeting_id',
        'title',
        'description',
        'assigned_to_id',
        'due_date',
        'is_completed',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
