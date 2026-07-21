<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domains\Shared\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ValidateTeamBuilderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::TeamsManage->value);
    }

    public function prepareForValidation(): void
    {
        if ($this->input('playersPerTeam') === null) {
            $this->merge([
                'playersPerTeam' => 5,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
            'season_id' => ['integer', 'exists:seasons,id'],
            'playersPerTeam' => ['integer', 'min:5'],
        ];
    }
}
