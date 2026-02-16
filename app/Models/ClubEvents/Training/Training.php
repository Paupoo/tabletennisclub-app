<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Training;

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
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
 * @property-read Room $room
 * @property-read Season|null $season
 * @property-write mixed $trainer_name
 * @property-read Collection<int, User> $trainees
 * @property-read int|null $trainees_count
 * @property-read User|null $trainer
 *
 * @method static Builder<static>|Training newModelQuery()
 * @method static Builder<static>|Training newQuery()
 * @method static Builder<static>|Training query()
 * @method static Builder<static>|Training whereCreatedAt($value)
 * @method static Builder<static>|Training whereEnd($value)
 * @method static Builder<static>|Training whereId($value)
 * @method static Builder<static>|Training whereLevel($value)
 * @method static Builder<static>|Training whereRoomId($value)
 * @method static Builder<static>|Training whereSeasonId($value)
 * @method static Builder<static>|Training whereStart($value)
 * @method static Builder<static>|Training whereTrainerId($value)
 * @method static Builder<static>|Training whereType($value)
 * @method static Builder<static>|Training whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Training extends Model
{
    use HasFactory;

    // TODO: implement Training factory or remove the using

    /**
     * The attributes with their data type in the application.
     *
     * @var array
     */
    protected $casts = [
        'end' => 'datetime',
        'level' => 'string',
        'room_id' => 'integer',
        'start' => 'datetime',
        'season_id' => 'integer',
        'trainer_id' => 'integer',
        'type' => 'string',
    ];

    /**
     * The attributes that are mass assignable
     *
     * @var array
     */
    protected $fillable = [
        'end',
        'level',
        'start',
        'type',
        'trainer_id',
        'room_id',
        'season_id',
    ];

    // Relationships

    /**
     * Relationship belongs to 1 room.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // TO CHECK COULD BE REMOVED???
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    // Mutators

    /**
     * Make sure case for trainer names is correct.
     *
     * @param string $value
     * @return void
     */
    public function setTrainerNameAttribute(string $value): void
    {
        $this->attributes['trainer_name'] = mb_convert_case($value, MB_CASE_TITLE);
    }

    public function trainees(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainingPack(): BelongsTo
    {
        return $this->belongsTo(TrainingPack::class);
    }

    // Accessors

}
