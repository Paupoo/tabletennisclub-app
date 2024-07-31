<?php

namespace App\Http\Controllers;

use App\Enums\TeamName;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Club;
use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('admin.teams.index', [
            'teams' => Team::orderby('name')->paginate(10),
            'team_model' => Team::class,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', Team::class);

        return view('admin.teams.create', [
            'users' => User::where('is_competitor', true)->orderby('force_index')->orderby('last_name')->orderby('first_name')->get(),
            'leagues' => League::join('seasons', 'leagues.season_id', '=', 'seasons.id')
                ->where('end_year', '>=', Carbon::now()->format('Y'))
                ->orderBy('seasons.end_year', 'asc')
                ->orderBy('level')
                ->orderBy('category')
                ->orderBy('division')
                ->get(),
            'team_names' => TeamName::cases(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamRequest $request)
    {
        $request = $request->validated();
        $league_model = League::find($request['league_id']);
        $team_model = new Team([
            'name' => $request['name'],
        ]);

        $team_model->season()->associate(Season::find($league_model->season_id));
        $team_model->club()->associate(Club::firstWhere('licence', 'BBW214'));
        $team_model->league()->associate($league_model);
        $team_model->save();
        
        if(isset($request['captain_id'])) {
            $captain = User::find($request['captain_id']);
            $team_model->captain()->associate($captain);
        } else {
            $team_model->captain()->dissociate();
        }
        $team_model->save();

        $team_model->users()->sync($request['players']);

        return redirect()->route('teams.index')->with('success', 'The team ' . $request['name'] . ' has been created.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        return view('admin.teams.show', [
            'team' => Team::find($id)->load('users'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $this->authorize('update', Team::class);

        $team = Team::findOrFail($id);
        //
        return view('admin.teams.edit', [
            'team' => $team,
            'team_names' => TeamName::cases(),
            'users' => User::where('is_competitor', true)->orderby('force_index')->orderby('last_name')->orderby('first_name')->get(),
            'leagues' => League::all(),
            'attachedUsers' => $team->users->pluck('id')->toArray(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeamRequest $request, string $id): RedirectResponse
    {
        $request = $request->validated();
        
        $team_model = Team::find($id);
        $league_model = League::find($request['league_id']);

        $team_model->name = $request['name'];
        $team_model->league()->associate($league_model);
        $team_model->season()->associate($league_model->season_id);
        isset($request['captain_id'])
            ? $team_model->captain()->associate(User::find($request['captain_id']))
            : $team_model->captain()->dissociate();

        $team_model->save();

        isset($request['players'])
            ? $team_model->users()->sync($request['players'])
            : $team_model->users()->detach();
        

        return redirect()->route('teams.index')->with('success', 'The team ' . $team_model->name . ' has been updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $team = Team::find($id);
        
        // Delete the team.
        $team->delete();

        return redirect()->route('teams.index')->with('deleted', 'The team ' . $team->name . ' has been deleted.');
    }

    /**
     * Get competitors ordered by ranking, then by last name, both descending.
     *
     * @return Collection
     */
    private function getCompetitors(): Collection
    {
        return User::where('is_competitor', '=', true)->orderby('force_index', 'desc')
            ->orderby('last_name', 'desc')->get();
    }

    /**
     * Return the amount of players that are competitors.
     *
     * @return integer
     */
    protected function countTotalCompetitors(): int
    {
        return User::where('is_competitor', '=', true)->count();
    }

    /**
     * Return the amound of teams based on a specified amount of players per team.
     *
     * @param integer $playersPerTeam
     * @return integer
     */
    protected function countTotalTeams(int $playersPerTeam): int
    {
        // Divide total competitors by wished kern size, return euclydian division (with a rest)
        $total_teams = intdiv($this->countTotalCompetitors(), $playersPerTeam);

        return $total_teams;
    }

    /**
     * Count players that are not in a team based on a specified amount of players per team.
     *
     * @param integer $playersPerTeam
     * @return integer
     */
    private function countPlayersWithoutTeam(int $playersPerTeam): int
    {
        // Count the rest of total competitors divided kern size.
        return $this->countTotalCompetitors() % $playersPerTeam;
    }

    /**
     * Return the "teams bulk builder" view enriched with the players spread by teams for the user to validate.
     *
     * @param Request $request
     * @return View
     */
    public function proposeTeamsCompositions(Request $request): View
    {

        // Validate the request
        $request->validate([
            'playersPerTeam' => 'integer|required|between:5,10',
            'season' => 'string|required',
        ]);

        $season = $request->season;
        $competitors = $this->getCompetitors();
        $teamNames = range('A', 'Z'); // A, B, C, ..., Z
        $poolTeamName = 'Pool';
        $playersPerTeam = $request->playersPerTeam; // Specify the desired number of players per team

        $teams = [];

        // Make sure every competitors has a force index
        foreach ($competitors as $competitor) {
            if ($competitor->force_index == null) {
                throw new Exception(__('At least one competitor is missing a force index. Please run the "set force index" from members admin'), 851);
            } else {
            }
        }

        foreach ($teamNames as $teamName) {
            $team = [];

            for ($i = 0; $i < $playersPerTeam && $competitors->count() > 0; $i++) {
                $team[] = $competitors->pop();
            }

            // Add the team to $teams only if it has players
            if (!empty($team)) {
                // Use the letter only if a team is full
                if (count($team) == $playersPerTeam) {
                    $teams[] = [
                        $teamName => $team,
                    ];
                    // Else, name it differently
                } elseif (count($team) < $playersPerTeam) {
                    $teams[] = [
                        $poolTeamName => $team,
                    ];
                }
            }
        }

        return view('admin/teams/bulk-composer', [
            'teams' => $teams,
            'season' => $season,
            'playersPerTeam' => $playersPerTeam,
        ]);
    }

    /**
     * Save in bulk the teams and associate the desired amount of players of player to each of them.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function saveTeamsCompositions(Request $request): RedirectResponse
    {

        // Validate and store wished kern size
        $request->validate([
            'playersPerTeam' => 'integer|required|between:5,10',
            'season' => 'string|required',
        ]);

        $competitors = $this->getCompetitors();

        // Make sure every competitors has a force index
        foreach ($competitors as $competitor) {
            if ($competitor->force_index == null) {
                throw new Exception(__('At least one competitor is missing a force index. Please run the "set force index" from members admin'), 851);
            } else {
            }
        }

        $season = $request->season;
        $playersPerTeam = $request->playersPerTeam;
        $totalTeams = $this->countTotalTeams($playersPerTeam);
        $teamName = 'A';

        for ($i = 0; $i < $totalTeams; $i++) {
            $team = new Team([
                'name' => $teamName,
                'season' => $season,
                'division' => 'To do',
            ]);

            $team->save();

            for ($j = 0; $j < $playersPerTeam; $j++) {
                $competitor = $competitors->pop();
                $team->users()->save($competitor);
            }

            $teamName++;
        }

        return redirect()->route('teams.index')->with('success', 'New teams for the season ' . date('Y', strtotime(now())) . ' - ' . date('Y', strtotime(now())) + 1 . ' have been created.');
    }
}
