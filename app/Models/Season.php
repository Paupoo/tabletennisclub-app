<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $start_at
 * @property int $end_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Interclub> $interclubs
 * @property-read int|null $interclubs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\League> $leagues
 * @property-read int|null $leagues_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Team> $teams
 * @property-read int|null $teams_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Training> $trainings
 * @property-read int|null $trainings_count
 *
 * @method static \Database\Factories\SeasonFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereEndYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereStartYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Season whereUpdatedAt($value)
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

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(Training::class);
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
