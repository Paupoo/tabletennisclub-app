<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Tournament;

use App\Models\ClubAdmin\Users\User;
use Database\Factories\ClubEvents\Tournament\PoolFactory;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $tournament_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tournament $tournament
 * @property-read Collection<int, TournamentMatch> $tournamentmatches
 * @property-read int|null $tournamentmatches_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static PoolFactory factory($count = null, $state = [])
 * @method static Builder<static>|Pool newModelQuery()
 * @method static Builder<static>|Pool newQuery()
 * @method static Builder<static>|Pool query()
 * @method static Builder<static>|Pool whereCreatedAt($value)
 * @method static Builder<static>|Pool whereId($value)
 * @method static Builder<static>|Pool whereName($value)
 * @method static Builder<static>|Pool whereTournamentId($value)
 * @method static Builder<static>|Pool whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Pool extends Model
{
    /** @use HasFactory<PoolFactory> */
    use HasFactory;

    protected $casts = [
        'name' => 'string',
    ];

    protected $fillable = [
        'name',
    ];


    /**
     * Utiliser les événements du modèle pour intercepter
     *  les attachements d'utilisateurs via la relation
     * @param User $user
     * @return void
     * @throws Exception
     */
    public function attachUser(User $user): User
    {
        // Vérifier si l'utilisateur est déjà dans un pool de ce tournoi
        $existingPool = $this->tournament->pools()
            ->whereHas('users', function (Builder $query) use ($user): void {
                $query->where('users.id', $user->id);
            })
            ->where('pools.id', '!=', $this->id)
            ->first();

        if ($existingPool) {
            throw new Exception("L'utilisateur fait déjà partie du pool '{$existingPool->name}' dans ce tournoi.");
        }

        // Sinon, attacher l'utilisateur à ce pool
        $this->users()->attach($user->id);
        return $user;
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
