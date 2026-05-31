<?php

declare(strict_types=1);

namespace App\Domains\Shared\Events\Trainings;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPack;

class TrainingPackEnrolled
{
    public function __construct(
        public readonly TrainingPack $trainingPack,
        public readonly User $user,
    ) {}
}
