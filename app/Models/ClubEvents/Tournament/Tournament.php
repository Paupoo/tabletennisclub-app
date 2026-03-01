<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Tournament;

use App\Casts\MoneyCast;
use App\Enums\TournamentStatusEnum;
use App\Events\Tournament\NewTournamentPublished;
use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use App\Observers\TournamentObserver;
use Database\Factories\ClubAdmin\Contact\ClubEvents\Tournament\TournamentFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int $total_users
 * @property int $max_users
 * @property mixed $price
 * @property TournamentStatusEnum $status
 * @property bool $has_handicap_points
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
        'price' => MoneyCast::class,
        'total_users' => 'integer',
        'max_users' => 'integer',
        'status' => TournamentStatusEnum::class,
        'has_handicap_points' => 'boolean',
    ];

    protected $dispatchesEvents = [
        'created' => NewTournamentPublished::class,
    ];

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'price',
        'total_users',
        'max_users',
        'status',
        'has_handicap_points',
    ];

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
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
     *
     * @param  string  $value
     * @return void
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
            ])
            ->using(TableTournament::class)
            ->withTimestamps();

    }

    /* Relations */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['has_paid', 'matches_won', 'sets_won', 'points_won'])
            ->withTimestamps();
    }
}
