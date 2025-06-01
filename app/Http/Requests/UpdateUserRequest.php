<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Ranking;
use App\Enums\Sex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_comittee_member;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ranking' => __('The ranking field is required and if the user is a competitor, it can\'t be "NA".'),
        ];
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
            'birthdate' => ['sometimes', 'date'],
            'city_code' => ['sometimes', 'string', 'digits:4'],
            'city_name' => ['sometimes', 'string'],
            'email' => ['required', 'email:rfc,dns,spoof,filter_unicode', 'unique:users,email,' . $this->route()->user->id],
            'first_name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_admin' => ['required', 'boolean'],
            'is_comittee_member' => ['required', 'boolean'],
            'is_competitor' => ['required', 'boolean'],
            'last_name' => ['required', 'string', 'max:255'],
            'licence' => ['nullable', 'required_if:is_competitor,true', 'unique:users,licence,' . $this->route()->user->id, 'size:6'],
            'phone_number' => ['nullable', 'string', 'digits_between:9,20'],
            'ranking' => [
                'required',
                Rule::when(
                    $this->input('is_competitor'),              // If the "is_competitor" is true
                    Rule::in($rankings_enum->reject('NA')),     // Don't allow NA as the player must have a ranking...
                ),
            ],
            'sex' => ['required', Rule::in(collect(Sex::cases())->pluck('name'))],
            'street' => ['sometimes', 'string'],
            'team_id' => ['nullable', 'exists:teams,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator): void {
            if ($this->input('is_competitor') && ! $this->input('is_active')) {
                $validator->errors()->add('is_competitor', __('A competitor must be active.'));
            }
            if (! $this->input('is_active') && $this->input('is_competitor')) {
                $validator->errors()->add('is_active', 'Un utilisateur actif est requis pour être compétiteur.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->input('is_active') !== null,
            'is_admin' => $this->input('is_admin') !== null,
            'is_comittee_member' => $this->input('is_comittee_member') !== null,
            'is_competitor' => $this->input('is_competitor') !== null,
        ]);
    }
}
