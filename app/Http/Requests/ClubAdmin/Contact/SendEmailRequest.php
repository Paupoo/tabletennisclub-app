<?php

declare(strict_types=1);

namespace App\Http\Requests\ClubAdmin\Contact;

use App\Domains\Shared\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can(Permission::ContactsManage->value);
    }

    public function messages(): array
    {
        return [
            'template' => __('Something went wrong with the templates.'),
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
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
}
