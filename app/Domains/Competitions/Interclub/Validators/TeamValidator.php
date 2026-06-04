<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Validators;

use Illuminate\Support\Facades\Validator;

class TeamValidator
{
    public function validateForCreation(array $data): array
    {
        return Validator::make($data, [
            'name' => 'required|string|min:2|max:255|unique:teams,name',
            'captain_id' => 'required|exists:users,id',
            'league_id' => 'required|exists:leagues,id',
            'season_id' => 'required|exists:seasons,id',
        ])->validate();
    }

    public function validateForUpdate(array $data): array
    {
        return Validator::make($data, [
            'name' => 'sometimes|required|string|min:2|max:255',
            'captain_id' => 'sometimes|required|exists:users,id',
            'league_id' => 'sometimes|required|exists:leagues,id',
        ])->validate();
    }
}
