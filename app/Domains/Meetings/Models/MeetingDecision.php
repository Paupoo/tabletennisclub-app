<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use Database\Factories\Domains\Meetings\Models\MeetingDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A decision recorded in a meeting's minutes, in markdown.
 *
 * Tied to the agenda point it was taken on, or to none — "outside the agenda".
 * Decisions are numbered D1, D2… across the whole meeting, in `sort_order`.
 *
 * @property int $id
 * @property int $meeting_id
 * @property int|null $agenda_item_id
 * @property string $body
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 * @property-read MeetingAgendaItem|null $agendaItem
 */
#[UseFactory(MeetingDecisionFactory::class)]
class MeetingDecision extends Model
{
    /** @use HasFactory<MeetingDecisionFactory> */
    use HasFactory;

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected $fillable = [
        'meeting_id',
        'agenda_item_id',
        'body',
        'sort_order',
    ];

    /** @return BelongsTo<MeetingAgendaItem, $this> */
    public function agendaItem(): BelongsTo
    {
        return $this->belongsTo(MeetingAgendaItem::class, 'agenda_item_id');
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
