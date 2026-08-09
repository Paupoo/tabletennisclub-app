<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Database\Factories\Domains\Competitions\Interclub\Models\SeasonFactory;
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
 * @property Carbon $start_at
 * @property Carbon $end_at
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
 * @property bool $is_active
 * @property bool $affiliations_open
 * @property-read Collection<int, Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read Collection<int, TrainingPack> $trainingPacks
 * @property-read int|null $training_packs_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static Builder<static>|Season active()
 * @method static Builder<static>|Season whereEndAt($value)
 * @method static Builder<static>|Season whereIsActive($value)
 * @method static Builder<static>|Season whereAffiliationsOpen($value)
 * @method static Builder<static>|Season whereStartAt($value)
 *
 * @mixin \Eloquent
 */
class Season extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'name' => 'string',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
        'affiliations_open' => 'boolean',
    ];

    protected $fillable = [
        'name',
        'start_at',
        'end_at',
        'is_active',
        'affiliations_open',
    ];

    public static function current(): ?self
    {
        return Cache::remember(
            'season.current',
            now()->addHours(1),
            fn () => static::active()->first()
        );
    }

    public function activate(): void
    {
        DB::transaction(function (): void {
            self::query()->where('id', '!=', $this->id)->update(['is_active' => false]);
            self::query()->whereKey($this->id)->update(['is_active' => true]);
            $this->is_active = true;
        });
    }

    public function closeAffiliations(): void
    {
        $this->update(['affiliations_open' => false]);
        Cache::forget('season.current');
    }

    // Relationships

    public function interclubs(): HasMany
    {
        return $this->hasMany(Interclub::class);
    }

    public function isCurrent(): bool
    {
        return $this->is_active;
    }

    public function isFuture(): bool
    {
        return $this->start_at > now() && ! $this->is_active;
    }

    public function isPast(): bool
    {
        return $this->end_at < now() && ! $this->is_active;
    }

    public function leagues(): HasMany
    {
        return $this->hasMany(League::class);
    }

    public function openAffiliations(): void
    {
        $this->update(['affiliations_open' => true]);
        Cache::forget('season.current');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

    #[\Override]
    protected static function booted(): void
    {
        static::saving(function (self $season): void {
            if ($season->start_at >= $season->end_at) {
                throw new \DomainException('start_at must be before end_at');
            }

            // Reject any season whose date range overlaps an existing one.
            // Standard overlap condition: A.start < B.end AND A.end > B.start
            $overlaps = static::query()
                ->when($season->exists, fn (Builder $q) => $q->where('id', '!=', $season->id))
                ->where('start_at', '<', $season->end_at)
                ->where('end_at', '>', $season->start_at)
                ->exists();

            if ($overlaps) {
                throw new \DomainException('Season dates overlap with an existing season.');
            }
        });
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'name' => 'string',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }
}
