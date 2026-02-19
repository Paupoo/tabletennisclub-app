<?php

declare(strict_types=1);

namespace App\Http\Requests\ClubAdmin\Contact;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendCustomEmailRequest extends FormRequest
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
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'send_copy' => 'boolean',
        ];
    }

    /**
     * Will force the bool for send_copy instead of string
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'send_copy' => $this->boolean('send_copy'),
        ]);
    }
}
