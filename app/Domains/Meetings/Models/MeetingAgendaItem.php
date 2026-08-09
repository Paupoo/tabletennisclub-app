<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\Meetings\Models\MeetingAgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int $sort_order
 * @property string $title
 * @property string|null $description
 * @property Carbon|null $discussed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 *
 * @method static \Database\Factories\Domains\Meetings\Models\MeetingAgendaItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingAgendaItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[UseFactory(MeetingAgendaItemFactory::class)]
class MeetingAgendaItem extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'sort_order' => 'integer',
        'discussed_at' => 'datetime',
    ];

    protected $fillable = [
        'meeting_id',
        'sort_order',
        'title',
        'description',
        'discussed_at',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
