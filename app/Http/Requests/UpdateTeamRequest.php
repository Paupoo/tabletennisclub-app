<?php

namespace App\Http\Requests;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\TeamName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_comittee_member;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'season_id' => [
                'required',
                'integer',
                'exists:seasons,id',
            ],
            'name' => [
                'required',
                Rule::in(array_column(TeamName::cases(), 'name')),
            ],
            'category' => [
                'required',
                Rule::in(array_column(LeagueCategory::cases(), 'name')),
            ],
            'level' => [
                'required',
                Rule::in(array_column(LeagueLevel::cases(), 'name')),
            ],
            'division' => [
                'required',
                'string'
            ],
            'players' => [
                'required',
                'array',
                'min:5',
            ],
            'players.*' => [
                'exists:users,id'
            ],
            'captain_id' => [
                'integer',
                'exists:users,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'players' => 'A team must contain at least 5 players',
        ];
    }
}
