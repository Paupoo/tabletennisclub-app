<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Training extends Model
{
    use HasFactory;

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

    // Relationships
    
    /**
     * Relationship belongs to 1 room.
     *
     * @return BelongsTo
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->as('trainees');
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

    // Accessors
    
}
