<?php

namespace App\Http\Requests;

use App\Enums\LeagueCategory;
use App\Models\League;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterclubRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_comittee_member || $this->user()->captainOf()->where('id', $this->input('team_id'))->exists();
    }

    protected function prepareForValidation(): void
    {
        // Capitalize team names
        $this->merge([
            'opposite_team_name' => strtoupper($this->input('opposite_team_name')),
        ]);

        // Remove Room_id if null
        if ($this->input('room_id') === null) {
            $this->offsetUnset('room_id');
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_visited' => [
                'boolean',
            ],
            'opposite_club_id' => [
                'integer',
                'required',
                'exists:clubs,id',
            ],
            'opposite_team_name' => [
                'required',
                'string',
                'regex:/^[a-zA-Z]{1}$/',
            ],
            'room_id' => [
                Rule::when(
                    isset($this->input()['is_visited']),
                    'required',
                    'prohibited',
                'integer',
                'exists:rooms,id',
            ),
            ],
            'start_date_time' => [
                'date',
                'required',
            ],
            'team_id' => [
                'integer',
                'required',
                'exists:teams,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'room_id' => __('If you receive another club, select a room. If you play outside, do not select a room.'),
        ];
    }
}
