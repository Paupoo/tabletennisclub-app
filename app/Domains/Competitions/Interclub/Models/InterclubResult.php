<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\Shared\Enums\InterclubResultEnum;
use Database\Factories\Domains\Competitions\Interclub\Models\InterclubResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $interclub_id
 * @property int $team_id
 * @property int $season_id
 * @property Carbon|null $match_date
 * @property int|null $week_number
 * @property bool $is_home
 * @property string|null $opponent_name
 * @property string|null $score
 * @property InterclubResultEnum|null $result
 * @property bool $is_bye
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Interclub|null $interclub
 * @property-read Team $team
 * @property-read Season $season
 *
 * @method static InterclubResultFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InterclubResult newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InterclubResult newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InterclubResult query()
 *
 * @mixin \Eloquent
 */
class InterclubResult extends Model
{
    use HasFactory;

    protected $casts = [
        'match_date' => 'date',
        'result' => InterclubResultEnum::class,
        'is_home' => 'boolean',
        'is_bye' => 'boolean',
    ];

    protected $fillable = [
        'interclub_id',
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

    protected $table = 'interclub_results';

    public function interclub(): BelongsTo
    {
        return $this->belongsTo(Interclub::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
