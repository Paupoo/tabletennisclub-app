<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Meeting;

use Database\Factories\ClubEvents\Meeting\MeetingAgendaItemFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
