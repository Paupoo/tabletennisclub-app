<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingCancellationType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $level
 * @property string $type
 * @property Carbon $start
 * @property Carbon $end
 * @property int $room_id
 * @property int|null $trainer_id
 * @property int $season_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $training_pack_id
 * @property string $status
 * @property string|null $cancellation_note
 * @property Carbon|null $cancelled_at
 * @property-read Room $room
 * @property-read Season|null $season
 * @property-read Collection<int, User> $trainees
 * @property-read int|null $trainees_count
 * @property-read User|null $trainer
 * @property-read TrainingPack|null $trainingPack
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCancellationNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCancelledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereTrainingPackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
