<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * 
 *
 * @property int $id
 * @property int|null $tournament_id
 * @property int|null $table_id
 * @property int|null $tournament_match_id
 * @property int $is_table_free
 * @property \Illuminate\Support\Carbon|null $match_started_at
 * @property string|null $match_ended_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereIsTableFree($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereMatchEndedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereMatchStartedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereTableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereTournamentMatchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TableTournament whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TableTournament extends Pivot
{
    protected $casts = [
        'match_started_at' => 'datetime:Y-m-d\TH:i',
    ];

    protected $fillable = [
        'is_table_free',
        'tournament_match_id',
        'match_started_at',
    ];
}
