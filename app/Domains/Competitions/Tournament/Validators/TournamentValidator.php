<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Validators;

use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TournamentValidator
{
    public function validateForCreation(array $data): array
    {
        return Validator::make($data, [
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'image' => 'nullable|url',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'exists:rooms,id',
            'max_users' => 'required|integer|min:10|max:500',
            'price' => 'required|numeric|min:0|max:10000',
            'has_handicap_points' => 'required|boolean',
            'status' => ['required', Rule::enum(TournamentStatusEnum::class)],
        ])->validate();
    }

    public function validateForUpdate(array $data): array
    {
        return Validator::make($data, [
            'name' => 'sometimes|required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'image' => 'nullable|url',
            'start_date' => 'sometimes|required|date|after:today',
            'end_date' => 'sometimes|required|date|after:start_date',
            'room_ids' => 'sometimes|required|array|min:1',
            'room_ids.*' => 'exists:rooms,id',
            'max_users' => 'sometimes|required|integer|min:10|max:500',
            'price' => 'sometimes|required|numeric|min:0|max:10000',
            'has_handicap_points' => 'sometimes|required|boolean',
            'status' => ['sometimes', 'required', Rule::enum(TournamentStatusEnum::class)],
        ])->validate();
    }
}
