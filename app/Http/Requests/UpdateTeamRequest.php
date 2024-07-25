<?php

namespace App\Http\Requests;

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
            'name' => ['required', Rule::in(array_column(TeamName::cases(), 'name'))],
            'league_id' => ['required','exists:leagues,id'],
            'players.*' => ['exists:users,id'],
            'captain_id' => ['exists:users,id'],
        ];
    }
}
