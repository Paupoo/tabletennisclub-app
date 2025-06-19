<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $division
 * @property string $level
 * @property string $category
 * @property int $season_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read \App\Models\Season $season
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 *
 * @method static \Database\Factories\LeagueFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereCategory($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereDivision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereSeasonId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|League whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class League extends Model
{
    use HasFactory;

    protected $casts = [
        'division' => 'string',
        'level' => 'string',
        'category' => 'string',
        'season_id' => 'integer',
    ];

    protected $fillable = [
        'division',
        'level',
        'category',
        'season_id',
    ];

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
