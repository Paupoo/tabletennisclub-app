<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    /** @use HasFactory<\Database\Factories\TournamentFactory> */
    use HasFactory;

    protected $fillable = [
        'total_users',
        'max_users',
    ];

    protected $casts = [
        'start_date' => 'datetime:Y-m-d\TH:i',
        'number_of_pools' => 'integer',
        'max_users' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['has_paid', 'matches_won', 'sets_won', 'points_won'])
            ->withTimestamps();
    }

    public function pools(): HasMany
    {
        return $this->hasMany(Pool::class);
    }

}
