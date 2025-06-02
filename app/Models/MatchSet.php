<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSet extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'tournament_match_id',
        'set_number',
        'player1_score',
        'player2_score',
        'winner_id',
    ];

    /**
     * Get the match this set belongs to
     */
    public function poolMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class);
    }

    /**
     * Get the winner of this set
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
