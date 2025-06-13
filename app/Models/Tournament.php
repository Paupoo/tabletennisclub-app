<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\TournamentStatusEnum;
use App\Events\NewTournamentPublished;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $start_date
 * @property \Illuminate\Support\Carbon|null $end_date
 * @property int $total_users
 * @property int $max_users
 * @property mixed $price
 * @property TournamentStatusEnum $status
 * @property bool $has_handicap_points
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property mixed $0
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentMatch> $matches
 * @property-read int|null $matches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Pool> $pools
 * @property-read int|null $pools_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Room> $rooms
 * @property-read int|null $rooms_count
 * @property-read \App\Models\TableTournament|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Table> $tables
 * @property-read int|null $tables_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\TournamentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament search($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereHasHandicapPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereMaxUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereTotalUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Tournament whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Tournament extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentFactory> */
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

    protected $dispatchesEvents = [
        'created' => NewTournamentPublished::class,
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
     * @param [type] $query
     * @param [type] $value
     * @return void
     */
    public function scopeSearch($query, $value)
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
