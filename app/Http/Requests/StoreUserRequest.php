<?php

namespace App\Http\Requests;

use App\Enums\Ranking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as RulesPassword;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Forbid member to create a user.
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
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8', RulesPassword::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'is_competitor' => ['nullable'],
            'licence' => ['nullable', 'required_if:is_competitor,true', 'unique:users,licence', 'size:6'],
            'ranking' => ['nullable', 'required_if:is_competitor,true', Rule::in(array_column(Ranking::cases(), 'value'))],
            'team_id' => ['nullable', 'exists:teams,id'],
            'is_admin' => ['nullable'],
            'is_comittee_member' => ['nullable'],
        ];
    }
}
