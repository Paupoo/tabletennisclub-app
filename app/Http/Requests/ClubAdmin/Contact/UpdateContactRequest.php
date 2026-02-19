<?php

declare(strict_types=1);

namespace App\Http\Requests\ClubAdmin\Contact;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_committee_member;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    'new',
                    'pending',
                    'processed',
                    'rejected',
                ]),
            ],
        ];
    }
}
