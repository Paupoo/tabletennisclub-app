<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'building_name',
        'street',
        'city_code',
        'city_name',
        'floor',
        'access_description',
        'capacity_for_trainings',
        'capacity_for_interclubs',
    ];

    protected $casts = [
        'name' => 'string',
        'building_name' => 'string',
        'street' => 'string',
        'city_code' => 'string',
        'city_name' => 'string',
        'floor' => 'string',
        'access_description' => 'string',
        'capacity_for_trainings' => 'integer',
        'capacity_for_interclubs'  => 'integer',
    ];

    public function training(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function clubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class);
    }

    
}
