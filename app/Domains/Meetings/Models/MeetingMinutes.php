<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Database\Factories\Domains\Meetings\Models\MeetingMinutesFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property array|null $announcements
 * @property array|null $decisions
 * @property string|null $notes
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property Carbon|null $sent_to_committee_at
 * @property Carbon|null $sent_to_all_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \App\Domains\Meetings\Models\Meeting $meeting
 * @property-read User|null $publisher
 * @method static \Database\Factories\Domains\Meetings\Models\MeetingMinutesFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereAnnouncements($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereDecisions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereIsPublished($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes wherePublishedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereSentToAllAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereSentToCommitteeAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingMinutes whereUpdatedAt($value)
 * @mixin \Eloquent
 */
#[UseFactory(MeetingMinutesFactory::class)]
class MeetingMinutes extends Model
{
    use HasFactory;

    protected $casts = [
        'announcements' => 'array',
        'decisions' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'sent_to_committee_at' => 'datetime',
        'sent_to_all_at' => 'datetime',
    ];

    protected $fillable = [
        'meeting_id',
        'announcements',
        'decisions',
        'notes',
        'is_published',
        'published_at',
        'published_by',
        'sent_to_committee_at',
        'sent_to_all_at',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
