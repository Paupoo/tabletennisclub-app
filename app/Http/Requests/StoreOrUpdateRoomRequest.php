<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrUpdateRoomRequest extends FormRequest
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
                'unique:rooms,name,' . $this->room?->id,
            ],
            'street' => [
                'required',
                'string',
            ],
            'city_code' => [
                'required',
                'integer',
                'between:1000,9999',
            ],
            'city_name' => [
                'required',
                'string',
            ],
            'building_name' => [
                'string',
            ],
            'access_description' => [
                'string',
                'nullable',
            ],
            'capacity_for_trainings' => [
                'required',
                'integer',
                'max:999',
            ],
            'capacity_for_interclubs' => [
                'required',
                'integer',
                'max:999',
            ],
        ];
    }
}
