<?php

namespace App\Http\Requests;

use App\Enums\Ranking;
use App\Enums\Roles;
use App\Enums\Sex;
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
        return $this->user()->is_admin || $this->user()->is_comittee_member;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if($this->ranking === null) {
            $this->merge([
                'ranking' => Ranking::NA->name,
            ]);
        }

        // Forbid a player that plays in competition to not have a ranking.
        if($this->is_competitor && $this->ranking === Ranking::NA->name) {
            $this->merge([
                'ranking' => 'null'
            ]);
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
            //
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'sex' => ['required', Rule::in(collect(Sex::cases())->pluck('name'))],
            'email' => ['required', 'email:rfc,dns,spoof,filter_unicode', 'unique:users,email,'.$this->route('member'),],
            'is_competitor' => ['nullable'],
            'is_admin' => ['nullable'],
            'is_comittee_member' => ['nullable'],
            'licence' => ['nullable', 'required_if:is_competitor,true', 'unique:users,licence,'.$this->route('member'), 'size:6'],
            'ranking' => ['required_if:is_competitor,true', Rule::in(collect(Ranking::cases())->pluck('name'))],
        ];
    }
}
