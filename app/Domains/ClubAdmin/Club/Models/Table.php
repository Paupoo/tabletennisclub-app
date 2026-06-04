<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Club\Models;

use App\Domains\Competitions\Tournament\Models\TableTournament;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property Carbon|null $purchased_on
 * @property string|null $state
 * @property int|null $room_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, TournamentMatch> $match
 * @property-read int|null $match_count
 * @property-read Room|null $room
 * @property-read TableTournament|null $pivot
 * @property-read Collection<int, Tournament> $tournaments
 * @property-read int|null $tournaments_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table wherePurchasedOn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereUpdatedAt($value)
 * @property string|null $state_description
 * @property string|null $brand
 * @property string|null $model
 * @property bool|null $is_available
 * @method static \Database\Factories\Domains\ClubAdmin\Club\Models\TableFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereBrand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Table whereStateDescription($value)
 * @mixin \Eloquent
 */
class Table extends Model
{
    use HasFactory;

    protected $casts = [
        'name' => 'string',
        'brand' => 'string',
        'model' => 'string',
        'state' => 'string',
        'state_description' => 'string',
        'is_available' => 'boolean',
        'purchased_on' => 'datetime:d-m-Y',
    ];

    protected $fillable = [
        'name',
        'brand',
        'model',
        'state',
        'state_description',
        'is_available',
        'purchased_on',
        'room_id',
    ];

    public static function getStates(): array
    {
        return [
            ['id' => 'Good condition', 'name' => __('Good condition')],
            ['id' => 'Needs repair', 'name' => __('Needs repair')],
            ['id' => 'Out of service', 'name' => __('Out of service')],
        ];
    }

    public function match(): BelongsToMany
    {
        return $this->belongsToMany(TournamentMatch::class, 'table_tournament');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class)
            ->withPivot([
                'is_table_free',
                'tournament_match_id',
                'match_started_at',
            ])
            ->using(TableTournament::class)
            ->withTimestamps();
    }
}
