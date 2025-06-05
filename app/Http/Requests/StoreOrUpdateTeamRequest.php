<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\TeamName;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreOrUpdateTeamRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->is_admin || $this->user()->is_committee_member;
    }

    /**
     * Checks that no team with the same letter is already existing in the league.
     *
     * @throws ValidationException
     */
    public function isDuplicatedTeam(): void
    {
        $team = Team::select('teams.*')->join('leagues', 'teams.league_id', 'leagues.id')
            ->where('teams.name', $this->name)
            ->where('teams.season_id', $this->season_id)
            ->where('leagues.category', $this->category)
            ->where('leagues.division', $this->division)
            ->where('leagues.level', $this->level)
            ->first();

        if ($team !== null) {
            throw ValidationException::withMessages([
                'name' => __('This team already exists in this league.'),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'players' => __('At least 5 players must be selected'),
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'season_id' => [
                'required',
                'integer',
                'exists:seasons,id',
            ],
            'category' => [
                'required',
                'string',
                Rule::in(collect(LeagueCategory::cases())->pluck('name')),
            ],
            'level' => [
                'required',
                'string',
                Rule::in(collect(LeagueLevel::cases())->pluck('name')),
            ],
            'division' => [
                'required',
                'string',
            ],
            'name' => [
                'required',
                Rule::in(collect(TeamName::cases())->pluck('name')),
                Rule::unique('teams', 'name')
                    ->where('league_id', $this->input('league_id')),
            ],
            'players' => [
                'required',
                'array',
                'min:5',
            ],
            'players.*' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'captain_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
        ];
    }
}
