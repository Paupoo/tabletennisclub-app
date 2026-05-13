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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        'is_active',
        'registrations_open',
    ];

    protected $casts = [
        'name'               => 'string',
        'start_at'           => 'datetime',
        'end_at'             => 'datetime',
        'is_active'          => 'boolean',
        'registrations_open' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function activate():void
    {
        DB::transaction(function() {
            // Deactivate all other seasons
            self::query()->where('is_active', true)->update(['is_active' => false]);
            // Activate the current season
            $this->is_active = true;
            $this->save();
        });
    }

    public static function current(): ?self
    {
        return Cache::remember(
            'season.current',
            now()->addHours(1),
            fn () => static::active()->first()
        );
    }

    public function openRegistrations(): void
    {
        $this->update(['registrations_open' => true]);
        Cache::forget('season.current');
    }

    public function closeRegistrations(): void
    {
        $this->update(['registrations_open' => false]);
        Cache::forget('season.current');
    }

    protected static function booted()
    {
        static::saving(function ($season) {
            if ($season->start_at >= $season->end_at) {
                throw new \DomainException(__('start_at must be before end_at'));
            }
        });
    }

    public function isCurrent(): bool
    {
        return $this->is_active;
    }

    public function isPast(): bool
    {
        return $this->end_at < now() && !$this->is_active;
    }

    public function isFuture(): bool
    {
        return $this->start_at > now() && !$this->is_active;
    }

    // Relationships

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

    public function trainingPacks(): HasMany
    {
        return $this->hasMany(TrainingPack::class);
    }

    // Could be updated to hasmanythrough trainingPack ???
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
