<?php

namespace App\Http\Requests;

use App\Enums\Ranking;
use App\Enums\Sex;
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

    public function prepareForValidation(): void
    {
        $this->merge([
            'has_debt' => false,
            'is_active' => null !== $this->request->get('is_active'),
            'is_admin' => null !== $this->request->get('is_admin'),
            'is_comittee_member' => null !== $this->request->get('is_comittee_member'),
            'is_competitor' => null !== $this->request->get('is_competitor'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'birthdate' => ['sometimes', 'date'],
            'city_code' => ['sometimes', 'string', 'digits:4'],
            'city_name' => ['sometimes', 'string'],
            'email' => ['required', 'email:rfc,dns,spoof,filter_unicode', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_admin' => ['boolean'],
            'is_comittee_member' => ['boolean'],
            'is_competitor' => ['boolean'],
            'last_name' => ['required', 'string', 'max:255'],
            'licence' => ['nullable', 'required_if:is_competitor,true', 'unique:users,licence', 'size:6'],
            'password' => ['required', 'confirmed', 'min:8', RulesPassword::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
            'phone_number' => ['sometimes','string','digits_between:9,20'],
            'ranking' => ['nullable', 'required_if:is_competitor,true', Rule::in(collect(Ranking::cases())->pluck('name'))],
            'sex' => ['required', Rule::in(collect(Sex::cases())->pluck('name'))],
            'street' => ['sometimes', 'string'],
            'team_id' => ['nullable', 'exists:teams,id'],
        ];
    }
}
