<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Models\TournamentMatch|null $nextMatch
 * @property-read \App\Models\User|null $player1
 * @property-read \App\Models\User|null $player2
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MatchSet> $sets
 * @property-read int|null $sets_count
 * @property-read \App\Models\Tournament|null $tournament
 * @property-read \App\Models\User|null $winner
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KnockoutMatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KnockoutMatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|KnockoutMatch query()
 *
 * @mixin \Eloquent
 */
class KnockoutMatch extends Model
{
    protected $fillable = [
        'tournament_id',
        'round',
        'match_number',
        'player1_id',
        'player2_id',
        'winner_id',
        'status',
        'table_number',
        'next_match_id',
        'is_bronze_match',
    ];

    public function getSetsWon($playerId): int
    {
        return $this->sets->where('winner_id', $playerId)->count();
    }

    public function getTotalPoints($playerId): int
    {
        $totalPoints = 0;

        foreach ($this->sets as $set) {
            if ($set->player1_id === $playerId) {
                $totalPoints += $set->player1_score;
            } elseif ($set->player2_id === $playerId) {
                $totalPoints += $set->player2_score;
            }
        }

        return $totalPoints;
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function nextMatch(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'next_match_id');
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function sets()
    {
        return $this->hasMany(MatchSet::class, 'tournament_match_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
