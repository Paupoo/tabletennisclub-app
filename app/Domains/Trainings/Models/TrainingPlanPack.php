<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Models;

use Database\Factories\Domains\Trainings\Models\TrainingPlanPackFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $training_plan_id
 * @property int|null $source_training_pack_id
 * @property string $name
 * @property string|null $level
 * @property int|null $day_of_week
 * @property int|null $max_participants
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TrainingPlan $plan
 * @property-read TrainingPack|null $sourcePack
 * @property-read Collection<int, TrainingPlanAssignment> $assignments
 * @property-read int|null $assignments_count
 *
 * @method static \Database\Factories\Domains\Trainings\Models\TrainingPlanPackFactory factory($count = null, $state = [])
 * @method static Builder<static>|TrainingPlanPack newModelQuery()
 * @method static Builder<static>|TrainingPlanPack newQuery()
 * @method static Builder<static>|TrainingPlanPack query()
 * @method static Builder<static>|TrainingPlanPack whereCreatedAt($value)
 * @method static Builder<static>|TrainingPlanPack whereDayOfWeek($value)
 * @method static Builder<static>|TrainingPlanPack whereId($value)
 * @method static Builder<static>|TrainingPlanPack whereLevel($value)
 * @method static Builder<static>|TrainingPlanPack whereMaxParticipants($value)
 * @method static Builder<static>|TrainingPlanPack whereName($value)
 * @method static Builder<static>|TrainingPlanPack wherePosition($value)
 * @method static Builder<static>|TrainingPlanPack whereSourceTrainingPackId($value)
 * @method static Builder<static>|TrainingPlanPack whereTrainingPlanId($value)
 * @method static Builder<static>|TrainingPlanPack whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class TrainingPlanPack extends Model
{
    /** @use HasFactory<TrainingPlanPackFactory> */
    use HasFactory;

    protected $casts = [
        'training_plan_id' => 'integer',
        'source_training_pack_id' => 'integer',
        'day_of_week' => 'integer',
        'max_participants' => 'integer',
        'position' => 'integer',
    ];

    protected $fillable = [
        'training_plan_id',
        'source_training_pack_id',
        'name',
        'level',
        'day_of_week',
        'max_participants',
        'position',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingPlanAssignment::class);
    }

    /**
     * Number of members currently assigned to this plan pack.
     */
    public function currentCount(): int
    {
        return $this->assignments()->count();
    }

    /**
     * Whether the current head count exceeds the capacity.
     * A null capacity means the pack can never be over capacity.
     */
    public function isOverCapacity(): bool
    {
        if ($this->max_participants === null) {
            return false;
        }

        return $this->currentCount() > $this->max_participants;
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function sourcePack(): BelongsTo
    {
        return $this->belongsTo(TrainingPack::class, 'source_training_pack_id');
    }
}
