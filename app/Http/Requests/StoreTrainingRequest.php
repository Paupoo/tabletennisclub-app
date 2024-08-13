<?php

namespace App\Http\Requests;

use App\Enums\TrainingLevel;
use App\Enums\TrainingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingRequest extends FormRequest
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
            'end_date' => [
                'required',
                'date_format:Y-m-d',
                'after:start_date',
            ],
            'end_time' => [
                'required',
                'date_format:H:i',
                'after:start_time',
            ],
            'level' => [
                'required',
                'string',
                Rule::in(collect(TrainingLevel::cases())->pluck('name')),
            ],
            'room_id' => [
                'required',
                'integer',
                'exists:rooms,id',
            ],
            'start_date' => [
                'required',
                'date_format:Y-m-d',
                'before:end_date'
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
                'before:end_time'
            ],
            'season_id' => [
                'required',
                'integer',
                'exists:seasons,id'
            ],
            'trainer_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'type' => [
                'required',
                'string',
                Rule::in(collect(TrainingType::cases())->pluck('name')),
            ],
        ];
    }
}
