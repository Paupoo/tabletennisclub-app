<?php

namespace App\Http\Controllers;

use App\Enums\TeamName;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Models\Club;
use App\Models\League;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Exception;
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
            'teams' => Team::orderby('name')->with('captain')->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view('admin.teams.create', [
            'users' => User::where('is_competitor', true)->orderby('force_index')->orderby('last_name')->orderby('first_name')->get(),
            'leagues' => League::where('start_year', '>=', Carbon::now()->format('Y'))->orderBy('start_year')->orderBy('level')->orderBy('category')->orderBy('division')->get(),
            'team_names' => TeamName::cases(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeamRequest $request)
    {
        $request = $request->validated();
        $league = League::find($request['league_id']);
        $team = new Team([
            'name' => $request['name'],
        ]);

        $team->club()->associate(Club::firstWhere('licence', 'BBW214'))->save();
        $team->league()->associate($league);
        
        if(isset($request['captain_id'])) {
            $captain = User::find($request['captain_id']);
            $team->captain()->associate($captain);
        } else {
            $team->captain()->dissociate();
        }
        $team->save();

        $team->users()->sync($request['players']);

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
        //
        $request = $request->validated();
        
        $team = Team::find($id);

        $team->name = $request['name'];
        $team->league()->associate(League::find($request['league_id']));
        if (isset($request['captain_id'])) {
            $team->captain()->associate(User::find($request['captain_id']));
        } else {
            $team->captain()->dissociate();
        }
        $team->save();

        if(isset($request['players'])) {
            $team->users()->sync($request['players']);
        } else {
            $team->users()->detach();
        }
        

        return redirect()->route('teams.index')->with('success', 'The team ' . $team->name . ' has been updated.');
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
