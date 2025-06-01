<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Recurrence;
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

    public function prepareForValidation(): void
    {
        $this->merge([
            'room_id' => (int) $this->input('room_id'),
            'season_id' => (int) $this->input('season_id'),
            'trainer_id' => $this->filled('trainer_id') ? (int) $this->input('trainer_id') : null,
        ]);
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
                'nullable',
                'required_if:recurrence,' . Recurrence::DAILY->name,
                'required_if:recurrence,' . Recurrence::WEEKLY->name,
                'required_if:recurrence,' . Recurrence::BIWEEKLY->name,
                'date_format:Y-m-d',
                'after_or_equal:start_date',
                'after_or_equal:today',
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
            'recurrence' => [
                'string',
                'required',
                Rule::in(collect(Recurrence::cases())->pluck('name')),
            ],
            'room_id' => [
                'required',
                'integer',
                'exists:rooms,id',
            ],
            'start_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:end_date',
                'after_or_equal:today',
            ],
            'start_time' => [
                'required',
                'date_format:H:i',
                'before:end_time',
            ],
            'season_id' => [
                'required',
                'integer',
                'exists:seasons,id',
            ],
            'trainer_id' => [
                'nullable',
                'required_if:type,' . TrainingType::DIRECTED->name,
                'required_if:type,' . TrainingType::SUPERVISED->name,
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

    public function messages(): array
    {
        return [
            //
        ];
    }
}
