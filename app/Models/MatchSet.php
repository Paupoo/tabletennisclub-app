<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $tournament_match_id
 * @property int $set_number
 * @property int $player1_score
 * @property int $player2_score
 * @property int|null $winner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\TournamentMatch|null $poolMatch
 * @property-read \App\Models\User|null $winner
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet wherePlayer1Score($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet wherePlayer2Score($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet whereSetNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet whereTournamentMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MatchSet whereWinnerId($value)
 *
 * @mixin \Eloquent
 */
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
