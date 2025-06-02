<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    // Relationships

    /**
     * Relationship belongs to 1 room.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
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
