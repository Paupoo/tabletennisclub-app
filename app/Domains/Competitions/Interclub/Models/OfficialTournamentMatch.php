<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Database\Factories\Domains\Competitions\Interclub\Models\OfficialTournamentMatchFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One match a member of ours played in an official tournament.
 *
 * Never an interclub line, and never read by anything a captain sees: the
 * member's own results page is the only place the two are put side by side.
 *
 * @property int $id
 * @property int $season_id
 * @property string $player_licence
 * @property string $player_name
 * @property string|null $player_ranking
 * @property int|null $user_id
 * @property Carbon $played_on
 * @property string $tournament_name
 * @property string|null $serie_name
 * @property string|null $opponent_licence
 * @property string $opponent_name
 * @property string|null $opponent_ranking
 * @property string|null $opponent_club
 * @property int|null $our_sets
 * @property int|null $their_sets
 * @property bool $we_won
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Season $season
 * @property-read User|null $user
 *
 * @method static OfficialTournamentMatchFactory factory($count = null, $state = [])
 * @method static Builder<static>|OfficialTournamentMatch newModelQuery()
 * @method static Builder<static>|OfficialTournamentMatch newQuery()
 * @method static Builder<static>|OfficialTournamentMatch query()
 *
 * @mixin Eloquent
 */
class OfficialTournamentMatch extends Model
{
    use HasFactory;

    protected $casts = [
        'played_on' => 'date',
        'we_won' => 'boolean',
    ];

    protected $fillable = [
        'opponent_club',
        'opponent_licence',
        'opponent_name',
        'opponent_ranking',
        'our_sets',
        'played_on',
        'player_licence',
        'player_name',
        'player_ranking',
        'season_id',
        'serie_name',
        'their_sets',
        'tournament_name',
        'user_id',
        'we_won',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * The set score, our side first, or null when the federation gave none.
     */
    public function setScore(): ?string
    {
        if ($this->our_sets === null || $this->their_sets === null) {
            return null;
        }

        return $this->our_sets . '-' . $this->their_sets;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
