<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Tournament;

use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use Database\Factories\ClubAdmin\Contact\ClubEvents\Tournament\TournamentMatchFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $pool_id
 * @property int|null $tournament_id
 * @property int|null $table_id
 * @property int|null $player1_id
 * @property int $player1_handicap_points
 * @property int|null $player2_id
 * @property int $player2_handicap_points
 * @property int|null $winner_id
 * @property string|null $round
 * @property string $status
 * @property string|null $started_ad
 * @property int $match_order
 * @property Carbon|null $scheduled_time
 * @property int|null $table_number
 * @property int|null $next_match_id
 * @property int|null $bronze_match_id
 * @property int $is_bronze_match
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $player1
 * @property-read User|null $player2
 * @property-read Pool|null $pool
 * @property-read Collection<int, MatchSet> $sets
 * @property-read int|null $sets_count
 * @property-read Collection<int, Table> $table
 * @property-read int|null $table_count
 * @property-read Tournament|null $tournament
 * @property-read User|null $winner
 *
 * @method static TournamentMatchFactory factory($count = null, $state = [])
 * @method static Builder<static>|TournamentMatch fromBracket()
 * @method static Builder<static>|TournamentMatch fromPools()
 * @method static Builder<static>|TournamentMatch newModelQuery()
 * @method static Builder<static>|TournamentMatch newQuery()
 * @method static Builder<static>|TournamentMatch ordered()
 * @method static Builder<static>|TournamentMatch query()
 * @method static Builder<static>|TournamentMatch whereBronzeMatchId($value)
 * @method static Builder<static>|TournamentMatch whereCreatedAt($value)
 * @method static Builder<static>|TournamentMatch whereId($value)
 * @method static Builder<static>|TournamentMatch whereIsBronzeMatch($value)
 * @method static Builder<static>|TournamentMatch whereMatchOrder($value)
 * @method static Builder<static>|TournamentMatch whereNextMatchId($value)
 * @method static Builder<static>|TournamentMatch wherePlayer1HandicapPoints($value)
 * @method static Builder<static>|TournamentMatch wherePlayer1Id($value)
 * @method static Builder<static>|TournamentMatch wherePlayer2HandicapPoints($value)
 * @method static Builder<static>|TournamentMatch wherePlayer2Id($value)
 * @method static Builder<static>|TournamentMatch wherePoolId($value)
 * @method static Builder<static>|TournamentMatch whereRound($value)
 * @method static Builder<static>|TournamentMatch whereScheduledTime($value)
 * @method static Builder<static>|TournamentMatch whereStartedAd($value)
 * @method static Builder<static>|TournamentMatch whereStatus($value)
 * @method static Builder<static>|TournamentMatch whereTableId($value)
 * @method static Builder<static>|TournamentMatch whereTableNumber($value)
 * @method static Builder<static>|TournamentMatch whereTournamentId($value)
 * @method static Builder<static>|TournamentMatch whereUpdatedAt($value)
 * @method static Builder<static>|TournamentMatch whereWinnerId($value)
 *
 * @mixin Eloquent
 */
class TournamentMatch extends Model
{
    //
    use HasFactory;

    protected $casts = [
        'scheduled_time' => 'datetime',
        'player1_handicap_points' => 'integer',
        'player2_handicap_points' => 'integer',
    ];

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

    /**
     * Get total sets won by a player in this match
     */
    public function getSetsWon(int $playerId): int
    {
        return $this->sets->where('winner_id', $playerId)->count();
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
            } elseif ($playerId === $this->player2_id) {
                $points += $set->player2_score;
            }
        }

        return $points;
    }

    /**
     * Check if the match is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the match is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
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
     * Get the pool this match belongs to
     */
    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class);
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
                'winner_id' => $setWinnerId,
            ]);
        }

        // Set the match winner
        $this->winner_id = ($player1SetsWon > $player2SetsWon) ? $this->player1_id : $this->player2_id;
        $this->status = 'completed';
        $this->save();
    }

    public function scopeFromBracket(Builder $query): void
    {
        $query->whereNotNull('round');
    }

    public function scopeFromPools(Builder $query): void
    {
        $query->whereNotNull('pool_id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('match_order')
            ->orderBy('pool_id')
            ->orderBy('round');
    }

    /**
     * Get the sets for this match
     */
    public function sets(): HasMany
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

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * Get the winner of the match
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }
}
