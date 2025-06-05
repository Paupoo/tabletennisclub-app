<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int|null $league_id
 * @property int|null $club_id
 * @property int|null $captain_id
 * @property int $season_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $captain
 * @property-read \App\Models\Club|null $club
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read \App\Models\League|null $league
 * @property-read \App\Models\Season $season
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @method static \Database\Factories\TeamFactory factory($count = null, $state = [])
 * @method static Builder<static>|Team inClub()
 * @method static Builder<static>|Team newModelQuery()
 * @method static Builder<static>|Team newQuery()
 * @method static Builder<static>|Team notInClub()
 * @method static Builder<static>|Team query()
 * @method static Builder<static>|Team whereCaptainId($value)
 * @method static Builder<static>|Team whereClubId($value)
 * @method static Builder<static>|Team whereCreatedAt($value)
 * @method static Builder<static>|Team whereId($value)
 * @method static Builder<static>|Team whereLeagueId($value)
 * @method static Builder<static>|Team whereName($value)
 * @method static Builder<static>|Team whereSeasonId($value)
 * @method static Builder<static>|Team whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Team extends Model
{
    use HasFactory;

    protected $casts = [
        'name' => 'string',
    ];

    protected $fillable = [
        'captain_id',
        'club_id',
        'league_id',
        'name',
        'season_id',
    ];

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function scopeInClub(Builder $query): void
    {
        $query->whereHas('club', fn (Builder $subquery) => $subquery->where('licence', '=', config('app.club_licence')));
    }

    public function scopeNotInClub(Builder $query): void
    {
        $query->whereHas('club', fn (Builder $subquery) => $subquery->where('licence', '!=', config('app.club_licence')));
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
