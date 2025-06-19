<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $tournament_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Tournament $tournament
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TournamentMatch> $tournamentmatches
 * @property-read int|null $tournamentmatches_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\PoolFactory factory($count = null, $state = [])
 * @method static Builder<static>|Pool newModelQuery()
 * @method static Builder<static>|Pool newQuery()
 * @method static Builder<static>|Pool query()
 * @method static Builder<static>|Pool whereCreatedAt($value)
 * @method static Builder<static>|Pool whereId($value)
 * @method static Builder<static>|Pool whereName($value)
 * @method static Builder<static>|Pool whereTournamentId($value)
 * @method static Builder<static>|Pool whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Pool extends Model
{
    /** @use HasFactory<\Database\Factories\PoolFactory> */
    use HasFactory;

    protected $casts = [
        'name' => 'string',
    ];

    protected $fillable = [
        'name',
    ];

    /**
     * Utiliser les événements du modèle pour intercepter
     * les attachements d'utilisateurs via la relation
     */
    public function attachUser(User $user)
    {
        // Vérifier si l'utilisateur est déjà dans un pool de ce tournoi
        $existingPool = $this->tournament->pools()
            ->whereHas('users', function (Builder $query) use ($user): void {
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

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function tournamentmatches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'pool_user');
    }
}
