<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Meeting;

use App\Models\ClubAdmin\Users\User;
use Database\Factories\ClubEvents\Meeting\MeetingMinutesFactory;
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
