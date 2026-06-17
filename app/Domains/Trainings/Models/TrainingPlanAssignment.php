<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Database\Factories\Domains\Trainings\Models\TrainingPlanAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $training_plan_id
 * @property int|null $training_plan_pack_id
 * @property int $user_id
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TrainingPlan $plan
 * @property-read TrainingPlanPack|null $pack
 * @property-read User $user
 *
 * @method static \Database\Factories\Domains\Trainings\Models\TrainingPlanAssignmentFactory factory($count = null, $state = [])
 * @method static Builder<static>|TrainingPlanAssignment newModelQuery()
 * @method static Builder<static>|TrainingPlanAssignment newQuery()
 * @method static Builder<static>|TrainingPlanAssignment query()
 * @method static Builder<static>|TrainingPlanAssignment whereCreatedAt($value)
 * @method static Builder<static>|TrainingPlanAssignment whereId($value)
 * @method static Builder<static>|TrainingPlanAssignment wherePosition($value)
 * @method static Builder<static>|TrainingPlanAssignment whereTrainingPlanId($value)
 * @method static Builder<static>|TrainingPlanAssignment whereTrainingPlanPackId($value)
 * @method static Builder<static>|TrainingPlanAssignment whereUpdatedAt($value)
 * @method static Builder<static>|TrainingPlanAssignment whereUserId($value)
 *
 * @mixin \Eloquent
 */
class TrainingPlanAssignment extends Model
{
    /** @use HasFactory<TrainingPlanAssignmentFactory> */
    use HasFactory;

    protected $casts = [
        'training_plan_id' => 'integer',
        'training_plan_pack_id' => 'integer',
        'user_id' => 'integer',
        'position' => 'integer',
    ];

    protected $fillable = [
        'training_plan_id',
        'training_plan_pack_id',
        'user_id',
        'position',
    ];

    public function pack(): BelongsTo
    {
        return $this->belongsTo(TrainingPlanPack::class, 'training_plan_pack_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TrainingPlan::class, 'training_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
