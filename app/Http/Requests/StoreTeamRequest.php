<?php

namespace App\Http\Requests;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\TeamName;
use App\Models\Team;
use GuzzleHttp\Psr7\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


class StoreTeamRequest extends FormRequest
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
            'season_id' => [
                'required',
                'integer',
                'exists:seasons,id'
            ],
            'category' => [
                'required',
                'string',
                Rule::in(array_column(LeagueCategory::cases(),'name')),
            ],
            'level' => [
                'required',
                'string',
                Rule::in(array_column(LeagueLevel::cases(), 'name'))
            ],
            'division' => [
                'required',
                'string',
            ],
            'name' => [
                'required',
                Rule::in(array_column(TeamName::cases(), 'name')),
                Rule::unique('teams', 'name')
                    ->where('league_id', $this->input('league_id'))
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
                'integer',
                'exists:users,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'players' => __('At least 5 players must be selected.'),
        ];
    }

    /**
     * Checks that no team with the same letter is already existing in the league.
     *
     * @return void
     * @throws ValidationException
     */
    public function isDuplicatedTeam(): void // hasAlreadyThisTeam//isTeamAlreadyExisting
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
                'name' => __('This team already exists in this league.')
            ]);
        }
    }
}
