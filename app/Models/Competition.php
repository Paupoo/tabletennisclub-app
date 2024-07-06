<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'team_id',
        'opposing_team'
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
        // 'team_id' => 'integer',
        'opposing_team' => 'string',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
