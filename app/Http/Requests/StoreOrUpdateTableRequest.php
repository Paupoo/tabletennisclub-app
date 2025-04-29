<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrUpdateTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_comittee_member;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'unique:tables,name,' . $this->id,
            ],
            'purchased_on' => [
                'nullable',
                'date',
            ],
            'state' => [
                'nullable',
                'string',
            ],
            'room_id' => [
                'required',
                'integer',
                'exists:rooms,id'
            ],
        ];
    }
}
