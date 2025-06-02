<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $casts = [
        'name' => 'string',
    ];

    protected $fillable = [
        'captain_id',
        'club_id',
        'league_id',
        'name',
        'season_id',
    ];

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function scopeInClub(Builder $query): void
    {
        $query->whereHas('club', fn (Builder $subquery) => $subquery->where('licence', '=', config('app.club_licence')));
    }

    public function scopeNotInClub(Builder $query): void
    {
        $query->whereHas('club', fn (Builder $subquery) => $subquery->where('licence', '!=', config('app.club_licence')));
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
