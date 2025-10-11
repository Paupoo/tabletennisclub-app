<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $level
 * @property string $type
 * @property \Illuminate\Support\Carbon $start
 * @property \Illuminate\Support\Carbon $end
 * @property int $room_id
 * @property int|null $trainer_id
 * @property int $season_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Room $room
 * @property-read \App\Models\Season|null $season
 * @property-write mixed $trainer_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $trainees
 * @property-read int|null $trainees_count
 * @property-read \App\Models\User|null $trainer
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereTrainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Training whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Training extends Model
{
    use HasFactory;

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

    public function trainingPack(): BelongsTo
    {
        return $this->belongsTo(TrainingPack::class);
    }

    // Mutators

    /**
     * Make sure case for trainer names is correct.
     *
     * @param [type] $value
     * @return void
     */
    public function setTrainerNameAttribute($value)
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

    // Accessors

}
