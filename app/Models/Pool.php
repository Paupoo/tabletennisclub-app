<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pool extends Model
{
    /** @use HasFactory<\Database\Factories\PoolFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $casts = [
        'name' => 'string',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pool_user');
    }

    public function tournamentmatches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    /**
     * Utiliser les événements du modèle pour intercepter 
     * les attachements d'utilisateurs via la relation
     */
    public function attachUser(User $user)
    {
        // Vérifier si l'utilisateur est déjà dans un pool de ce tournoi
        $existingPool = $this->tournament->pools()
            ->whereHas('users', function (Builder $query) use ($user) {
                $query->where('users.id', $user->id);
            })
            ->where('pools.id', '!=', $this->id)
            ->first();

        if ($existingPool) {
            throw new \Exception("L'utilisateur fait déjà partie du pool '{$existingPool->name}' dans ce tournoi.");
        }

        // Si non, attacher l'utilisateur à ce pool
        return $this->users()->attach($user->id);
    }
}
