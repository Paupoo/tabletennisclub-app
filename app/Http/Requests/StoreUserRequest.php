<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Ranking;
use App\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_committee_member;
    }

    public function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->input('is_active') !== null,
            'is_admin' => $this->input('is_admin') !== null,
            'is_committee_member' => $this->input('is_committee_member') !== null,
            'is_competitor' => $this->input('is_competitor') !== null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rankings_enum = collect(Ranking::cases())->pluck('name');

        return [
            'birthdate' => ['nullable', 'date'],
            'city_code' => ['nullable', 'string', 'digits:4'],
            'city_name' => ['nullable', 'string'],
            'email' => ['required', 'email:rfc,dns,spoof,filter_unicode', 'unique:users,email'],
            'first_name' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_admin' => ['boolean'],
            'is_committee_member' => ['boolean'],
            'is_competitor' => ['boolean'],
            'last_name' => ['required', 'string', 'max:255'],
            'licence' => ['nullable', 'required_if:is_competitor,true', 'unique:users,licence', 'size:6'],
            // 'password' => ['required', 'confirmed', 'min:8', Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()],
            'phone_number' => ['nullable', 'string', 'digits_between:9,20'],
            'ranking' => [
                'nullable',
                'required_if:is_competitor,true',
                Rule::when(
                    $this->input('ranking') !== null,              // If the "is_competitor" is true
                    Rule::in($rankings_enum->reject('NA')),     // Don't allow NA as the player must have a ranking...
                    Rule::in($rankings_enum),
                ),
            ],
            'gender' => ['required', Rule::in(collect(Gender::cases())->pluck('name'))],
            'street' => ['nullable', 'string'],
            'team_id' => ['nullable', 'exists:teams,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'licence.unique' => __('The licence is already used'),
        ];
    }
}
