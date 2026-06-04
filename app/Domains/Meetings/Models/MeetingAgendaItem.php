<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use Database\Factories\Domains\Meetings\Models\MeetingAgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int $sort_order
 * @property string $title
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Domains\Meetings\Models\Meeting $meeting
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
 * @mixin \Eloquent
 */
#[UseFactory(MeetingAgendaItemFactory::class)]
class MeetingAgendaItem extends Model
{
    use HasFactory;

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'meeting_id',
        'sort_order',
        'title',
        'description',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
