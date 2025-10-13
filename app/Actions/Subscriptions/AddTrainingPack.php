<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use App\Models\TrainingPack;
use Illuminate\Http\RedirectResponse;

class AddTrainingPack
{
    public function __invoke(TrainingPack $trainingPack, Subscription $subscription, bool $discount = false): void
    {
        $trainingPack->subscriptions()->attach($trainingPack->id, [
            'discount' => $discount,
        ]);
    }
}
