<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Interclub extends Model
{
    use HasFactory;

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function visitedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'visited_team_id');
    }

    public function visitingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'visiting_team_id');
    }
}
