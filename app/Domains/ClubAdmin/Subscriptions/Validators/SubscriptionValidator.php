<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Validators;

use Illuminate\Support\Facades\Validator;

class SubscriptionValidator
{
    public function validateForCreation(array $data): array
    {
        return Validator::make($data, [
            'user_id' => 'required|exists:users,id',
            'season_id' => 'required|exists:seasons,id',
            'training_pack_ids' => 'nullable|array',
            'training_pack_ids.*' => 'exists:training_packs,id',
        ])->validate();
    }

    public function validateForUpdate(array $data): array
    {
        return Validator::make($data, [
            'training_pack_ids' => 'nullable|array',
            'training_pack_ids.*' => 'exists:training_packs,id',
        ])->validate();
    }

    public function validateForPayment(array $data): array
    {
        return Validator::make($data, [
            'amount' => 'required|numeric|min:0.01|max:100000',
            'payment_method' => 'required|in:cash,bank_transfer,cheque',
        ])->validate();
    }
}
