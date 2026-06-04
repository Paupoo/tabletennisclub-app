<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Validators;

use Illuminate\Support\Facades\Validator;

class MeetingValidator
{
    public function validateForCreation(array $data): array
    {
        return Validator::make($data, [
            'title' => 'required|string|min:3|max:255',
            'type' => 'required|in:ag,committee,festive',
            'description' => 'nullable|string|max:1000',
            'format' => 'required|in:in-person,zoom,hybrid',
            'location' => 'nullable|string|max:255',
            'zoom_link' => 'nullable|url',
            'rsvp_deadline' => 'nullable|date|after:today',
        ])->validate();
    }

    public function validateForUpdate(array $data): array
    {
        return Validator::make($data, [
            'title' => 'sometimes|required|string|min:3|max:255',
            'type' => 'sometimes|required|in:ag,committee,festive',
            'description' => 'nullable|string|max:1000',
            'format' => 'sometimes|required|in:in-person,zoom,hybrid',
            'location' => 'nullable|string|max:255',
            'zoom_link' => 'nullable|url',
        ])->validate();
    }
}
