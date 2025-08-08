<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendEmailRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => [
                'required',
                Rule::in(['welcome', 'membership_info', 'polite_decline', 'request_info', 'custom']),
            ],
        ];
    }
    public function messages(): array
    {
        return [
            'template' => __('Something went wrong with the templates.'),
        ];
    }
}
