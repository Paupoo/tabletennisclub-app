<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TournamentMatch extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'pool_id',
        'table_id',
        'player1_id',
        'player2_id',
        'player1_handicap_points',
        'player2_handicap_points',
        'winner_id',
        'status', // 'scheduled', 'in_progress', 'completed'
        'match_order',
        'scheduled_time',
        'tournament_id',
        'round',
        'next_match_id',
        'bronze_match_id',
        'is_bronze_match',
    ];

    protected $casts = [
        'scheduled_time' => 'datetime',
        'player1_handicap_points' => 'integer',
        'player1_handicap_points' => 'integer',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
    /**
     * Get the pool this match belongs to
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
    }

    /**
     * Get player 1
     */
    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    /**
     * Get player 2
     */
    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    /**
     * Get the winner of the match
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    /**
     * Get the sets for this match
     */
    public function sets()
    {
        return $this->hasMany(MatchSet::class);
    }

     /**
     * Get the table for this match
     */
    public function table(): BelongsToMany
    {
        return $this->belongsToMany(Table::class, 'table_tournament');
    }

    public function scopeOrdered($query){
        $query->orderBy('match_order')
            ->orderBy('pool_id')
            ->orderBy('round');
    }

    public function scopeFromPools($query){
        $query->whereNotNull('pool_id');
    }

    public function scopeFromBracket($query){
        $query->whereNotNull('round');
    }

    /**
     * Check if the match is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if the match is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Record match result with sets
     */
    public function recordResult(array $setResults): void
    {
        $player1SetsWon = 0;
        $player2SetsWon = 0;

        // Delete any existing sets
        $this->sets()->delete();

        // Create new sets
        foreach ($setResults as $index => $result) {
            $player1Score = $result['player1_score'];
            $player2Score = $result['player2_score'];
            
            // Determine set winner
            $setWinnerId = null;
            if ($player1Score > $player2Score) {
                $setWinnerId = $this->player1_id;
                $player1SetsWon++;
            } else {
                $setWinnerId = $this->player2_id;
                $player2SetsWon++;
            }

            // Create the set
            $this->sets()->create([
                'set_number' => $index + 1,
                'player1_score' => $player1Score,
                'player2_score' => $player2Score,
                'winner_id' => $setWinnerId
            ]);
        }

        // Set the match winner
        $this->winner_id = ($player1SetsWon > $player2SetsWon) ? $this->player1_id : $this->player2_id;
        $this->status = 'completed';
        $this->save();
    }

    /**
     * Get total points for a player in this match
     */
    public function getTotalPoints(int $playerId): int
    {
        $sets = $this->sets;
        $points = 0;

        foreach ($sets as $set) {
            if ($playerId === $this->player1_id) {
                $points += $set->player1_score;
            } else if ($playerId === $this->player2_id) {
                $points += $set->player2_score;
            }
        }

        return $points;
    }

    /**
     * Get total sets won by a player in this match
     */
    public function getSetsWon(int $playerId): int
    {
        return $this->sets->where('winner_id', $playerId)->count();
    }
}
