<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competition extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable
     *
     * @var array
     */
    protected $fillable = [
        'competition_date',
        'address',
        'total_players',
        'week_number',
        'team_visited',
        'team_visiting'
    ];

    /**
     * The attributes with their data type in the application.
     *
     * @var array
     */
    protected $casts = [
        'competition_date' => 'datetime',
        'address' => 'string',
        'total_players' => 'string',
        'week_number' => 'string',
        'team_visited' => 'string',
        'team_visiting' => 'string',
    ];
}
