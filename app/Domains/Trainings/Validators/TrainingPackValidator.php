<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Validators;

use Illuminate\Support\Facades\Validator;

class TrainingPackValidator
{
    public function validateForCreation(array $data): array
    {
        return Validator::make($data, [
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'trainer_id' => 'required|exists:users,id',
            'season_id' => 'required|exists:seasons,id',
            'day_of_week' => 'required|integer|between:0,6',
            'time' => 'required|date_format:H:i',
            'duration_minutes' => 'required|integer|min:30|max:180',
            'room_id' => 'required|exists:rooms,id',
            'price' => 'required|numeric|min:0|max:10000',
            'max_participants' => 'required|integer|min:1|max:100',
        ])->validate();
    }

    public function validateForUpdate(array $data): array
    {
        return Validator::make($data, [
            'name' => 'sometimes|required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'trainer_id' => 'sometimes|required|exists:users,id',
            'day_of_week' => 'sometimes|required|integer|between:0,6',
            'time' => 'sometimes|required|date_format:H:i',
            'duration_minutes' => 'sometimes|required|integer|min:30|max:180',
            'room_id' => 'sometimes|required|exists:rooms,id',
            'price' => 'sometimes|required|numeric|min:0|max:10000',
            'max_participants' => 'sometimes|required|integer|min:1|max:100',
        ])->validate();
    }
}
