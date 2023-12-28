<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'street',
        'city_code',
        'city_name',
        'building_name',
        'access_description',
        'capacity_trainings',
        'capacity_matches',
    ];

    public function training(): HasMany
    {
        return $this->hasMany(Training::class);
    }
}
