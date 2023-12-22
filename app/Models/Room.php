<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillabe = [
        'name',
        'street',
        'city_code',
        'city_name',
        'building_name',
        'access_description',
        'capacity_trainings',
        'capacity_matches',
    ];
}
