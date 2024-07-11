<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as RulesPassword;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(Request $request): bool
    {
        // Forbid member to create a user.
        return in_array($request->user()->role->id, [2,3]);
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
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8', RulesPassword::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'is_competitor' => ['nullable'],
            'licence' => ['nullable', 'unique:users,licence', 'size:6'],
            'ranking' => ['nullable', Rule::in([
                'B0',
                'B2',
                'B4',
                'B6',
                'C0',
                'C2',
                'C4',
                'C6',
                'D0',
                'D2',
                'D4',
                'D6',
                'E0',
                'E2',
                'E4',
                'E6',
                'NC',
            ])],
            'team_id' => ['nullable', 'exists:teams,id'],
            'role_id' => ['required','exists:roles,id'],
        ];
    }
}
