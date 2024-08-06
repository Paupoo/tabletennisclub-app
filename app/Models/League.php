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
        'season_id',
    ];

    protected $casts = [
        'division' => 'string',
        'level' => 'string',
        'category' => 'string',
        'season_id' => 'integer'
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
