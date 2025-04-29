<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Table extends Model
{
    protected $fillable = [
        'name',
        'purchased_on',
        'state',
        'room_id',
    ];

    protected $casts = [
        'name' => 'string',
        'purchased_on' => 'date',
        'state' => 'string',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
