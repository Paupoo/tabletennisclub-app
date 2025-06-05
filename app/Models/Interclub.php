<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeagueCategory;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 
 *
 * @property int $id
 * @property string $address
 * @property \Illuminate\Support\Carbon $start_date_time
 * @property int|null $week_number
 * @property int $total_players
 * @property string|null $score
 * @property string|null $result
 * @property int|null $visited_team_id
 * @property int|null $visiting_team_id
 * @property int|null $room_id
 * @property int|null $league_id
 * @property int|null $season_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\League|null $league
 * @property-read \App\Models\Room|null $room
 * @property-read \App\Models\Season|null $season
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \App\Models\Team|null $visitedTeam
 * @property-read \App\Models\Team|null $visitingTeam
 * @method static \Database\Factories\InterclubFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereLeagueId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereStartDateTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereTotalPlayers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereVisitedTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereVisitingTeamId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Interclub whereWeekNumber($value)
 * @mixin \Eloquent
 */
class Interclub extends Model
{
    use HasFactory;

    protected $casts = [
        'start_date_time' => 'datetime',
    ];

    protected $fillable = [
        'address',
        'start_date_time',
        'total_players',
        'visited_team_id',
        'visiting_team_id',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Set attribute to count total players needed to fill up one team.
     */
    public function setTotalPlayersPerteam(string $type): self
    {

        if (in_array($type, array_column(LeagueCategory::cases(), 'name'))) { // If the input exist in the Enum

            $total = match ($type) {
                LeagueCategory::MEN->name => 4,
                LeagueCategory::WOMEN->name => 3,
                LeagueCategory::VETERANS->name => 3,
            };

            $this->total_players = $total;

            return $this;
        } else {

            throw new Exception('This category is unknown and not allowed.');
        }
    }

    public function setWeekNumber(string $date): self
    {
        $this->week_number = Carbon::create($date)->isoWeek;

        return $this;
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_subscribed', 'is_selected', 'has_played')
            ->as('registration')
            ->withTimestamps();
    }

    public function visitedTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'visited_team_id');
    }

    public function visitingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'visiting_team_id');
    }
}
