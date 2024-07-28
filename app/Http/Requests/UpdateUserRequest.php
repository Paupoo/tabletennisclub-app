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
        if (empty($this->password)) {
            unset($request['password']);
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
            'sex' => ['required', Rule::in(array_column(Sex::cases(), 'name'))],
            'email' => ['required', 'email:rfc,dns,spoof,filter_unicode', 'unique:users,email,'.$this->route('member'),],
            'password' => ['nullable', 'confirmed', 'min:8', RulesPassword::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'is_competitor' => ['nullable'],
            'is_active' => ['nullable'],
            'is_admin' => ['nullable'],
            'is_comittee_member' => ['nullable'],
            'licence' => ['present_if:is_competitor,true', 'unique:users,licence,'.$this->route('member'), 'size:6'],
            'ranking' => ['present_if:is_competitor,true', Rule::in(array_column(Ranking::cases(),'name'))],            
        ];
    }
}
