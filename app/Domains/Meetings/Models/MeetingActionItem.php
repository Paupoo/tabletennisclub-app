<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Models\ClubAdmin\Users\User;
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
 */
#[UseFactory(MeetingActionItemFactory::class)]
class MeetingActionItem extends Model
{
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
