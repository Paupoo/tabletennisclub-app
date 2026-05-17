<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Training;

use App\Enums\TrainingCancellationType;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Training extends Model
{
    use HasFactory;

    protected $casts = [
        'start' => 'datetime',
        'end' => 'datetime',
        'cancelled_at' => 'datetime',
        'training_pack_id' => 'integer',
        'room_id' => 'integer',
        'season_id' => 'integer',
        'trainer_id' => 'integer',
    ];

    protected $fillable = [
        'level',
        'type',
        'start',
        'end',
        'trainer_id',
        'room_id',
        'season_id',
        'training_pack_id',
        'status',
        'cancellation_note',
        'cancelled_at',
    ];

    public function cancel(TrainingCancellationType $type, ?string $note = null): void
    {
        $this->update([
            'status' => $type === TrainingCancellationType::FREE ? 'cancelled_free' : 'cancelled_closed',
            'cancellation_note' => $note,
            'cancelled_at' => Carbon::now(),
        ]);
    }

    public function isCancelled(): bool
    {
        return $this->status !== 'scheduled';
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function trainees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function trainingPack(): BelongsTo
    {
        return $this->belongsTo(TrainingPack::class);
    }
}
