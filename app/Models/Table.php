<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Table extends Model
{
    protected $fillable = [
        'name',
        'purchased_on',
        'state',
        'room_id',
    ];

    protected $casts = [
        'name' => 'string',
        'purchased_on' => 'date',
        'state' => 'string',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class)
            ->withPivot([
                'is_table_free',
                'match_started_at',
            ])
            ->using(TableTournament::class)
            ->withTimestamps();
    }

    public function tournamentMatches(): BelongsToMany
    {
        return $this->belongsToMany(TournamentMatch::class);
    }
}
