<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TableTournament extends Pivot
{
    protected $fillable = [
        'is_table_free',
        'tournament_match_id',
        'match_started_at',
    ];

    protected $casts = [
        'match_started_at' => 'datetime:Y-m-d\TH:i',
    ];
}
