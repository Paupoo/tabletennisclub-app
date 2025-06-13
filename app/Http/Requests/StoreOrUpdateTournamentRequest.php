<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TournamentStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrUpdateTournamentRequest extends FormRequest
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
            'name' => 'required|string',
            'start_date' => 'required|date|after:today',
            'end_date' => 'required|date|after:start_date',
            'room_ids' => 'required|array|min:1',
            'max_users' => 'required|integer|min:10',
            'price' => 'decimal:0,2|min:0',
            'has_handicap_points' => 'required|bool',
            'status' => ['required', Rule::enum(TournamentStatusEnum::class)],
        ];
    }
}
