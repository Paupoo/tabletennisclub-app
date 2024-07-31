<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    use HasFactory;

    protected $fillable = [ 
        'division',
        'level',
        'category',
        'start_year',
        'end_year',
    ];

    protected $casts = [
        'division' => 'string',
        'level' => 'string',
        'category' => 'string',
        'start_year' => 'integer',
        'end_year' => 'integer',
    ];

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
