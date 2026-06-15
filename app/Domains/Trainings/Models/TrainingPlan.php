<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingPlanStatusEnum;
use Database\Factories\Domains\Trainings\Models\TrainingPlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $season_id
 * @property string $name
 * @property TrainingPlanStatusEnum $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Season $season
 * @property-read User|null $creator
 * @property-read Collection<int, TrainingPlanPack> $packs
 * @property-read int|null $packs_count
 * @property-read Collection<int, TrainingPlanAssignment> $assignments
 * @property-read int|null $assignments_count
 *
 * @method static \Database\Factories\Domains\Trainings\Models\TrainingPlanFactory factory($count = null, $state = [])
 * @method static Builder<static>|TrainingPlan newModelQuery()
 * @method static Builder<static>|TrainingPlan newQuery()
 * @method static Builder<static>|TrainingPlan query()
 * @method static Builder<static>|TrainingPlan whereCreatedAt($value)
 * @method static Builder<static>|TrainingPlan whereCreatedBy($value)
 * @method static Builder<static>|TrainingPlan whereId($value)
 * @method static Builder<static>|TrainingPlan whereName($value)
 * @method static Builder<static>|TrainingPlan whereSeasonId($value)
 * @method static Builder<static>|TrainingPlan whereStatus($value)
 * @method static Builder<static>|TrainingPlan whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TrainingPlan extends Model
{
    /** @use HasFactory<TrainingPlanFactory> */
    use HasFactory;

    protected $casts = [
        'season_id' => 'integer',
        'status' => TrainingPlanStatusEnum::class,
        'created_by' => 'integer',
    ];

    protected $fillable = [
        'season_id',
        'name',
        'status',
        'created_by',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingPlanAssignment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function packs(): HasMany
    {
        return $this->hasMany(TrainingPlanPack::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
}
