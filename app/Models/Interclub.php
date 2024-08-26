<?php

namespace App\Models;

use App\Enums\LeagueCategory;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Interclub extends Model
{
    use HasFactory;

    protected $fillable = [
        'address',
        'start_date_time',
        'total_players',
        'visited_team_id',
        'visiting_team_id',
    ];

    protected $casts = [
        'start_date_time' => 'datetime',
    ];

    /**
     * Set attribute to count total players needed to fill up one team.
     *
     * @param string $type
     * @return self
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
        $this->week_number = Carbon::create($date)->week;
        return $this;
    }

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
