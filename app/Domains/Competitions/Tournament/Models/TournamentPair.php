<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\Competitions\Tournament\Models\TournamentPairFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $player1_id
 * @property int $player2_id
 * @property int $registered_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $player1
 * @property-read User $player2
 * @property-read User $registeredBy
 * @property-read Tournament $tournament
 *
 * @method static \Database\Factories\Domains\Competitions\Tournament\Models\TournamentPairFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair wherePlayer1Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair wherePlayer2Id($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair whereRegisteredBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentPair whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TournamentPair extends Model
{
    use HasAuditLog;

    /** @use HasFactory<TournamentPairFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'player1_id',
        'player2_id',
        'registered_by',
    ];

    public function displayName(): string
    {
        $p1 = $this->player1?->last_name ?? '?';
        $p2 = $this->player2?->last_name ?? '?';

        return "{$p1}/{$p2}";
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    /**
     * The two rankings as they stand, e.g. "B2/C4".
     *
     * Deliberately not a single ranking: the handicap table shows the rungs are
     * not evenly spaced (B0→C0 is worth 3 points, B4→C4 only 2), so averaging
     * two rankings would name a level neither player holds. An unranked player
     * shows as NC, which is what an unranked affiliated player is.
     */
    public function rankingLabel(): string
    {
        return collect([$this->player1?->ranking, $this->player2?->ranking])
            ->map(fn (?Ranking $ranking): string => ($ranking ?? Ranking::NC)->getLabel())
            ->implode('/');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    /**
     * Seeding weight of the pair: the lower, the stronger.
     *
     * Sums both players' positions in {@see Ranking::cases()}, which runs from
     * the strongest (B0) to the weakest (NA). A sum orders pairs correctly
     * without claiming to name an average ranking. An unknown or missing
     * ranking weighs as the weakest rung, so a pair nobody has ranked is seeded
     * last rather than first.
     */
    public function seedIndex(): int
    {
        $cases = Ranking::cases();
        $weakest = count($cases);

        return collect([$this->player1?->ranking, $this->player2?->ranking])
            ->map(fn (?Ranking $ranking): int => $ranking === null ? $weakest : (int) array_search($ranking, $cases, true))
            ->sum();
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
