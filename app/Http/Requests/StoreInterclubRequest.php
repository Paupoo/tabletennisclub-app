<?php

namespace App\Http\Requests;

use App\Enums\LeagueCategory;
use App\Models\League;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterclubRequest extends FormRequest
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
        $league = League::whereRelation('teams', 'id', '=', $this->input('club_team'))->firstOrFail();
        
        $this->merge([
            'league_category' => $league->category,
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
            'address' => ['nullable', 'string', 'required_if:room_id,null'],
            'club_team' => ['integer','required', 'exists:teams,id'],
            'league_category' => ['required', 'string', Rule::in(collect(LeagueCategory::cases())->pluck('name'))],
            'opposing_team' => ['string','required','different:visited_team'],
            'room_id' => ['nullable', 'integer','required_if:address,null', 'exists:rooms,id'],
            'start_date_time' => ['date', 'required'],
        ];
    }
}
