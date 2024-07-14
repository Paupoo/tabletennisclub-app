<?php

namespace App\Http\Requests;

use App\Enums\Rankings;
use App\Enums\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as RulesPassword;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->role->name === Roles::ADMIN->value || $this->user()->role->name === Roles::COMITTEE_MEMBER->value;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'last_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'max:255', 'unique:users,email,'.$this->route('member'),],
            'password' => ['nullable', 'confirmed', 'min:8', RulesPassword::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'is_competitor' => ['nullable'],
            'licence' => ['nullable', 'unique:users,licence,'.$this->route('member'), 'size:6'],
            'ranking' => ['nullable', Rule::in(array_column(Rankings::cases(),'value'))],
            'team_id' => ['required', 'exists:teams,id'],
            'role_id' => ['integer'],
        ];
    }
}
