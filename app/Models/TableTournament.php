<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TableTournament extends Pivot
{
    protected $casts = [
        'match_started_at' => 'datetime:Y-m-d\TH:i',
    ];
}
