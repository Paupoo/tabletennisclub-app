<?php

declare(strict_types=1);

namespace App\Models\ClubAdmin\Club;

use App\Models\ClubEvents\Tournament\TableTournament;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $purchased_on
 * @property string|null $state
 * @property int|null $room_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubEvents\Tournament\TournamentMatch> $match
 * @property-read int|null $match_count
 * @property-read \App\Models\ClubAdmin\Club\Room|null $room
 * @property-read \App\Models\ClubEvents\Tournament\TableTournament|null $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubEvents\Tournament\Tournament> $tournaments
 * @property-read int|null $tournaments_count
 *
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
 *
 * @mixin \Eloquent
 */
class Table extends Model
{
    protected $casts = [
        'name' => 'string',
        'brand' => 'string',
        'model' => 'string',
        'state' => 'string',
        'state_description' => 'string',
        'is_available' => 'boolean',
        'is_competition_ready' => 'boolean',
        'purchased_on' => 'datetime:d-m-Y',
    ];

    protected $fillable = [
        'name',
        'brand',
        'model',
        'state',
        'state_description',
        'is_available',
        'is_competition_ready',
        'purchased_on',
        'room_id'
    ];

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

    public static function getStates(): array
    {
        return [
            ['id' => 'Good condition', 'name' => __('Good condition')],
            ['id' => 'Needs repair', 'name' => __('Needs repair')],
            ['id' => 'Out of service', 'name' => __('Out of service')],
        ];
    }
}
