<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Interclub;

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Training\Training;
use App\Models\ClubEvents\Training\TrainingPack;
use Database\Factories\ClubEvents\Interclub\SeasonFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property int $start_at
 * @property int $end_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read Collection<int, League> $leagues
 * @property-read int|null $leagues_count
 * @property-read Collection<int, Team> $teams
 * @property-read int|null $teams_count
 * @property-read Collection<int, Training> $trainings
 * @property-read int|null $trainings_count
 *
 * @method static SeasonFactory factory($count = null, $state = [])
 * @method static Builder<static>|Season newModelQuery()
 * @method static Builder<static>|Season newQuery()
 * @method static Builder<static>|Season query()
 * @method static Builder<static>|Season whereCreatedAt($value)
 * @method static Builder<static>|Season whereEndYear($value)
 * @method static Builder<static>|Season whereId($value)
 * @method static Builder<static>|Season whereName($value)
 * @method static Builder<static>|Season whereStartYear($value)
 * @method static Builder<static>|Season whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_at',
        'end_at',
    ];

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function leagues(): HasMany
    {
        return $this->hasMany(League::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    // Could be updated to hasmanythrough trainingPack ???
    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
    }

    public function trainingPacks(): HasMany
    {
        return $this->hasMany(TrainingPack::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subscriptions')
            ->withPivot('amount_due', 'is_competitive')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'name' => 'string',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }
}
