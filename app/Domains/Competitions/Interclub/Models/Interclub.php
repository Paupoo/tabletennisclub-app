<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Shared\Traits\HasAvailability;
use App\Observers\InterclubObserver;
use Carbon\Carbon;
use Database\Factories\Domains\Competitions\Interclub\Models\InterclubFactory;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $address
 * @property string|null $captain_message
 * @property \Illuminate\Support\Carbon $start_date_time
 * @property int|null $week_number
 * @property int $total_players
 * @property int|null $visited_team_id
 * @property int|null $visiting_team_id
 * @property int|null $room_id
 * @property int|null $league_id
 * @property int|null $season_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read League|null $league
 * @property-read Room|null $room
 * @property-read Season|null $season
 * @property-read Collection<int, Team> $teams
 * @property-read int|null $teams_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 * @property-read Team|null $visitedTeam
 * @property-read Team|null $visitingTeam
 *
 * @method static InterclubFactory factory($count = null, $state = [])
 * @method static Builder<static>|Interclub newModelQuery()
 * @method static Builder<static>|Interclub newQuery()
 * @method static Builder<static>|Interclub query()
 * @method static Builder<static>|Interclub whereAddress($value)
 * @method static Builder<static>|Interclub whereCaptainMessage($value)
 * @method static Builder<static>|Interclub whereCreatedAt($value)
 * @method static Builder<static>|Interclub whereId($value)
 * @method static Builder<static>|Interclub whereLeagueId($value)
 * @method static Builder<static>|Interclub whereRoomId($value)
 * @method static Builder<static>|Interclub whereSeasonId($value)
 * @method static Builder<static>|Interclub whereStartDateTime($value)
 * @method static Builder<static>|Interclub whereTotalPlayers($value)
 * @method static Builder<static>|Interclub whereUpdatedAt($value)
 * @method static Builder<static>|Interclub whereVisitedTeamId($value)
 * @method static Builder<static>|Interclub whereVisitingTeamId($value)
 * @method static Builder<static>|Interclub whereWeekNumber($value)
 *
 * @mixin Eloquent
 */
#[ObservedBy(InterclubObserver::class)]
class Interclub extends Model
{
    use HasAuditLog;
    use HasAvailability;
    use HasFactory;

    protected $casts = [
        'start_date_time' => 'datetime',
        'is_bye' => 'boolean',
    ];

    protected $fillable = [
        'address',
        'aftt_match_id',
        'captain_message',
        'is_bye',
        'league_id',
        'season_id',
        'start_date_time',
        'round_number',
        'total_players',
        'visited_team_id',
        'visiting_team_id',
        'week_number',
    ];

    /**
     * @param  array<int, int>  $teamIds
     * @return array<int, int> week_number => match_day (1-based)
     */
    public static function matchDayMap(int $seasonId, array $teamIds = []): array
    {
        $query = self::where('season_id', $seasonId)->whereNotNull('week_number');

        if ($teamIds) {
            $query->where(fn ($q) => $q
                ->whereIn('visited_team_id', $teamIds)
                ->orWhereIn('visiting_team_id', $teamIds));
        }

        return $query->orderBy('start_date_time')
            ->pluck('week_number')
            ->unique()
            ->values()
            ->flip()
            ->map(fn (int $i): int => $i + 1)
            ->toArray();
    }

    public function interclubResult(): HasOne
    {
        return $this->hasOne(InterclubResult::class);
    }

    /**
     * Whether this fixture is played by a team the given member captains.
     *
     * The relational half of "may compose this lineup": the permission says the
     * member may select at all, this says which fixtures they may select for.
     * Either side of the fixture counts — our team can be the visiting one.
     */
    public function isCaptainedBy(User $user): bool
    {
        $this->loadMissing(['visitedTeam', 'visitingTeam']);

        return in_array(
            $user->id,
            array_filter([$this->visitedTeam?->captain_id, $this->visitingTeam?->captain_id]),
            true,
        );
    }

    public function isHome(): bool
    {
        $this->loadMissing('visitedTeam.club');

        return (bool) $this->visitedTeam?->club?->is_own_club;
    }

    /**
     * Whether the captain has published a lineup for this fixture.
     *
     * Not `is_selected`, which the selection screen writes the moment a captain
     * drags a name in and which therefore describes a draft nobody has been
     * told about. `selection_confirmed_at` is stamped by the service that sends
     * the mails, so it is the only column that means "the team knows".
     *
     * Asked of the fixture rather than of one player: a player dropped from a
     * published lineup has their own stamp cleared, and the lineup is still
     * published for everyone else.
     */
    public function isLineupPublished(): bool
    {
        return $this->users()->wherePivotNotNull('selection_confirmed_at')->exists();
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function opponentTeam(): ?Team
    {
        $this->loadMissing(['visitedTeam', 'visitingTeam']);

        return $this->isHome() ? $this->visitingTeam : $this->visitedTeam;
    }

    public function ourTeam(): ?Team
    {
        $this->loadMissing(['visitedTeam.club', 'visitingTeam.club']);

        return $this->isHome() ? $this->visitedTeam : $this->visitingTeam;
    }

    /**
     * The side of this fixture the given member plays on.
     *
     * Deliberately not {@see ourTeam()}, which answers "which side is the
     * club's" and has no opinion when both are. Two of our teams meeting in the
     * same division is rare but legal, and on that day `ourTeam()` names the
     * home one for everybody — telling half the players they are at home when
     * they are the visitors. The roster is what settles it.
     */
    public function playerTeam(User $user): ?Team
    {
        $this->loadMissing(['visitedTeam.users', 'visitingTeam.users']);

        foreach ([$this->visitedTeam, $this->visitingTeam] as $team) {
            if ($team?->users->contains('id', $user->id)) {
                return $team;
            }
        }

        return null;
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Fixtures that are actually played.
     *
     * A bye is a round in which a team has no opponent. It is imported so the
     * results screens can show it — they already render "Bye" rather than a date
     * — but everywhere else it would appear as a dated match against nobody.
     *
     * A named scope rather than a global one: excluding rows by default is
     * invisible at the call site, and a year from now the question "why is this
     * fixture missing" should be answerable by reading the query.
     */
    public function scopeWithoutByes(Builder $query): void
    {
        $query->where('is_bye', false);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Set attribute to count total players needed to fill up one team.
     */
    public function setTotalPlayersPerTeam(string $type): self
    {

        if (in_array($type, array_column(LeagueCategory::cases(), 'name'))) { // If the input exist in the Enum

            $total = match ($type) {
                LeagueCategory::MEN->name => 4,
                LeagueCategory::WOMEN->name => 3,
                LeagueCategory::VETERANS->name => 3,
            };

            $this->total_players = $total;

            return $this;
        }
        throw new Exception('This category is unknown and not allowed.');
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
            ->withPivot('is_subscribed', 'is_selected', 'has_played', 'availability', 'availability_note', 'selection_confirmed_at')
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
