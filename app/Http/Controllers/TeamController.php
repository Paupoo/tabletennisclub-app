<?php

namespace App\Http\Controllers;

use App\Classes\HtmlFactory;
use App\Models\Team;
use App\Models\User;
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
        return view ('admin.teams.index', [
            'teams' => Team::orderby('name')->paginate(10),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view ('.admin.teams.create', [
            'users' => User::where('is_competitor', '=', true)->orderby('last_name')->get(),
            'seasons' => HtmlFactory::GetSeasonsHTMLDropdown(),
            'team_names' => HtmlFactory::GetTeamNames(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $request->validate([
            'season' => 'string',
            'name' => 'string',
            'division' => 'string',         
            // 'players.*' => 'nullable|exists:users,user_id',
        ]);

        $team = Team::create([
            'season' => $request->season,
            'name' => $request->name,
            'division' => $request->division,
        ]);

        foreach($request->players as $player){
            $player = User::find($player);
            $team->users()->save($player);
        }

        

        return redirect()->route('teams.index')->with('success', 'The team ' . $request->name . ' has been created.');
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
        //
        return view ('admin.teams.edit', [
            'team' => Team::find($id),
            'users' => User::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        //
        $request->validate([
            'season' => 'string',
            'name' => 'string',
            'division' => 'string',
        ]);

        $team = Team::find($id);

        $team->season = $request->season;
        $team->name = $request->name;
        $team->division = $request->division;

        $team->save();

        return redirect()->route('teams.index')->with('success', 'The team ' . $team->name . ' has been added.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $team = Team::find($id);
        $team->delete();

        return redirect()->route('teams.index')->with('deleted','The team has been '. $team->name . ' deleted.');
    }

    /**
     *  Define team functions
     */
    private function countTotalCompetitors(): int
    {
        return User::where('is_competitor', '=', true)->count();
    }

    private function countTotalTeams(int $kern_size): int
    {
        // Divide total competitors by wished kern size, return euclydian division (with a rest)
        $total_teams = intdiv($this->countTotalCompetitors(), $kern_size);

        return $total_teams;
    }

    private function countPlayersWithoutTeam(int $kern_size): int
    {
        // Count the rest of total competitors divided kern size.
        return $this->countTotalCompetitors() % $kern_size;
    }

    private function getCompetitors(): Collection
    {
        return User::where('is_competitor', '=', true)->orderby('force_index', 'asc')->orderby('ranking')->orderby('last_name')->get();
    }

    public function proposeTeamsAmount(): array
    {

        $kerns = [8, 7, 6, 5];

        foreach ($kerns as $kern) {
            $results['Kern with ' . $kern . ' team_players'] = [
                'Total teams' => $this->countTotalTeams($kern),
                'Remaining team_players' => $this->countPlayersWithoutTeam($kern),
            ];
        }

        return $results;
    }

    public function proposeTeamsCompositions(Request $request): View
    {

        // Validate and store wished kern size
        $request->validate([
            'kern_size' => ['integer'],
        ]);

        $kern = $request->kern_size;

        // Get competitors
        $competitors = $this->getCompetitors();

        // Make sure every competitors has a force index
        foreach ($competitors as $competitor) {
            if ($competitor->force_index == null) {
                throw new Exception(__('At least one competitor is missing a force index. Please run the "set force index" from members admin'), 851);
            } else {

            }
        }

        // Count total team
        $total_teams = $this->countTotalTeams($kern);

        // Count team_players without team
        $total_players_without_team = $this->countPlayersWithoutTeam($kern);

        // Teams should be named by a letter alphabetically
        $team_name = 'A';

        // Start variable will be use to determine the starting slices of competitors table for each team
        $start = 0;

        //Create the expected amount of teams and fill them with expected amount of players
        for ($i = 0; $i < $total_teams; $i++) {
            $team_players_count = 0;
            $team_players = array_slice($competitors->all(), $start, $kern);

            //Loop for each player
            foreach ($team_players as $player) {
                $results[$team_name]['Player ' . $team_players_count + 1] = [
                    trans(__('Last Name')) => $player['last_name'] . ' ' . $player['first_name'],
                    trans(__('Ranking')) => $player['ranking'],
                ];
                $team_players_count++;
            }

            $start = $start + $kern;
            $team_name++;
        }

        unset($player);

        // Add remaining players without teams
        $players_witout_teams = array_slice($competitors->all(), - $total_players_without_team, $total_players_without_team);

        $count = 0;
        foreach($players_witout_teams as $player) {
            $count++;
            $results['Players withtout a team']['Player ' . $count] = [
                trans(__('Name')) => $player['last_name'] . ' ' . $player['first_name'],
                trans(__('Ranking')) => $player['ranking'],
            ];
        }

        return view('admin/teams/bulk-composer', [
            'results' => collect($results),
        ]);
    }
}