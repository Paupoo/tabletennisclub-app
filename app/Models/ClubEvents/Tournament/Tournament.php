<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Tournament;

use App\Casts\MoneyCast;
use App\Enums\TournamentObjectiveEnum;
use App\Enums\TournamentStatusEnum;
use App\Events\Tournament\NewTournamentPublished;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubPosts\EventPost;
use App\Models\ClubPosts\NewsPost;
use App\Observers\TournamentObserver;
use Database\Factories\ClubEvents\Tournament\TournamentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $start_date
 * @property string|null $start_time
 * @property Carbon|null $registration_deadline
 * @property Carbon|null $end_date
 * @property int $total_users
 * @property int $max_users
 * @property mixed $price
 * @property TournamentStatusEnum $status
 * @property bool $has_handicap_points
 * @property int $duration_minutes
 * @property int $pool_size
 * @property int $nb_pools
 * @property int $nb_qualifiers_per_pool
 * @property int $sets_to_win
 * @property int $logistics_buffer_minutes
 * @property string $match_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TournamentMatch> $matches
 * @property-read int|null $matches_count
 * @property-read Collection<int, Pool> $pools
 * @property-read int|null $pools_count
 * @property-read Collection<int, Room> $rooms
 * @property-read int|null $rooms_count
 * @property-read TableTournament|null $pivot
 * @property-read Collection<int, Table> $tables
 * @property-read int|null $tables_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static TournamentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Tournament newModelQuery()
 * @method static Builder<static>|Tournament newQuery()
 * @method static Builder<static>|Tournament query()
 * @method static Builder<static>|Tournament search($value)
 * @method static Builder<static>|Tournament whereCreatedAt($value)
 * @method static Builder<static>|Tournament whereEndDate($value)
 * @method static Builder<static>|Tournament whereHasHandicapPoints($value)
 * @method static Builder<static>|Tournament whereId($value)
 * @method static Builder<static>|Tournament whereMaxUsers($value)
 * @method static Builder<static>|Tournament whereName($value)
 * @method static Builder<static>|Tournament wherePrice($value)
 * @method static Builder<static>|Tournament whereStartDate($value)
 * @method static Builder<static>|Tournament whereStatus($value)
 * @method static Builder<static>|Tournament whereTotalUsers($value)
 * @method static Builder<static>|Tournament whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
#[ObservedBy(TournamentObserver::class)]
class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    protected $casts = [
        'name',
        'start_date' => 'datetime:Y-m-d\TH:i',
        'end_date' => 'datetime:Y-m-d\TH:i',
        'registration_deadline' => 'datetime',
        'price' => MoneyCast::class,
        'total_users' => 'integer',
        'max_users' => 'integer',
        'status' => TournamentStatusEnum::class,
        'objective' => TournamentObjectiveEnum::class,
        'has_handicap_points' => 'boolean',
        'duration_minutes' => 'integer',
        'pool_size' => 'integer',
        'nb_pools' => 'integer',
        'nb_qualifiers_per_pool' => 'integer',
        'sets_to_win' => 'integer',
        'deuce_enabled' => 'boolean',
        'logistics_buffer_minutes' => 'integer',
    ];

    protected $dispatchesEvents = [
        'created' => NewTournamentPublished::class,
    ];

    protected $fillable = [
        'name',
        'start_date',
        'start_time',
        'end_date',
        'price',
        'total_users',
        'max_users',
        'status',
        'has_handicap_points',
        'duration_minutes',
        'pool_size',
        'nb_pools',
        'nb_qualifiers_per_pool',
        'sets_to_win',
        'deuce_enabled',
        'logistics_buffer_minutes',
        'match_type',
        'objective',
        'news_post_id',
        'registration_deadline',
    ];

    /** Count only active (registered/confirmed) participants, ignoring waitlist. */
    public function activeRegistrationsCount(): int
    {
        return $this->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->count();
    }

    public function eventPost(): MorphOne
    {
        return $this->morphOne(EventPost::class, 'eventable');
    }

    public function isPaid(): bool
    {
        return $this->price > 0;
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function newsPost(): BelongsTo
    {
        return $this->belongsTo(NewsPost::class);
    }

    public function nextWaitlistPosition(): int
    {
        return ($this->users()
            ->wherePivot('registration_status', 'waiting')
            ->max('tournament_user.waitlist_position') ?? 0) + 1;
    }

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->BelongsToMany(Room::class);
    }

    /** Scopes */

    /**
     * Scope search to search by last or first name
     */
    public function scopeSearch(Builder $query, string $value): void
    {
        $query->where('name', 'like', '%' . $value . '%')
            ->orWhere('price', 'like', '%' . $value . '%');
    }

    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(Table::class)
            ->withPivot([
                'is_table_free',
                'tournament_match_id',
                'match_started_at',
                'match_ended_at',
            ])
            ->using(TableTournament::class)
            ->withTimestamps();

    }

    /* Relations */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TournamentRegistration::class)
            ->withPivot([
                'id', 'has_paid',
                'registration_status', 'waitlist_position',
                'confirmation_deadline', 'payment_deadline', 'payment_id',
            ])
            ->withTimestamps();
    }

    /**
     * Validate a single set score against this tournament's scoring rules.
     * Returns null if valid, or a translated error string.
     *
     * $p1Handicap / $p2Handicap are the per-set starting scores (Approach B AFTT).
     * Each player's final score must be ≥ their handicap starting value.
     */
    public function validateSetScore(int $p1, int $p2, int $setNumber, int $p1Handicap = 0, int $p2Handicap = 0): ?string
    {
        if ($p1 < $p1Handicap) {
            return __("Set :n: player 1's score (:score) cannot be below their starting handicap (:min).", ['n' => $setNumber, 'score' => $p1, 'min' => $p1Handicap]);
        }

        if ($p2 < $p2Handicap) {
            return __("Set :n: player 2's score (:score) cannot be below their starting handicap (:min).", ['n' => $setNumber, 'score' => $p2, 'min' => $p2Handicap]);
        }

        $max = max($p1, $p2);
        $min = min($p1, $p2);

        if ($this->deuce_enabled) {
            // Standard TT: win at 11 with ≥2-point lead; deuce if both reach 10+
            if ($min < 10) {
                if ($max !== 11) {
                    return __('Set :n: the winner must reach exactly 11 points (:score).', ['n' => $setNumber, 'score' => "{$p1}-{$p2}"]);
                }
            } else {
                // Deuce: both reached 10+, must win by exactly 2
                if ($max - $min !== 2) {
                    return __('Set :n: at deuce, win by exactly 2 points — e.g. 12-10, 13-11 (:score).', ['n' => $setNumber, 'score' => "{$p1}-{$p2}"]);
                }
            }
        } else {
            // Simplified: first to 11 wins, no deuce (10-10 → 11-10 is valid)
            if ($max !== 11 || $max === $min) {
                return __('Set :n: first to 11 points wins the set (:score).', ['n' => $setNumber, 'score' => "{$p1}-{$p2}"]);
            }
        }

        return null;
    }

    public function waitlistCount(): int
    {
        return $this->users()
            ->wherePivot('registration_status', 'waiting')
            ->count();
    }
}
