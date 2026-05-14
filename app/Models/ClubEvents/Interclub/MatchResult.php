<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Interclub;

use App\Enums\InterclubResult;
use Database\Factories\ClubEvents\Interclub\MatchResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $season_id
 * @property Carbon|null $match_date
 * @property int|null $week_number
 * @property bool $is_home
 * @property string|null $opponent_name
 * @property string|null $score
 * @property InterclubResult|null $result
 * @property bool $is_bye
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Season $season
 *
 * @method static MatchResultFactory factory($count = null, $state = [])
 */
class MatchResult extends Model
{
    use HasFactory;

    protected $casts = [
        'match_date' => 'date',
        'result' => InterclubResult::class,
        'is_home' => 'boolean',
        'is_bye' => 'boolean',
    ];

    protected $fillable = [
        'team_id',
        'season_id',
        'match_date',
        'week_number',
        'is_home',
        'opponent_name',
        'score',
        'result',
        'is_bye',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
