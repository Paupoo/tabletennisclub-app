<?php

namespace App\Http\Controllers;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Enums\TeamName;
use App\Http\Requests\InitiateTeamBuilderRequest;
use App\Http\Requests\StoreTeamRequest;
use App\Http\Requests\UpdateTeamRequest;
use App\Http\Requests\ValidateTeamBuilderRequest;
use App\Models\Club;
use App\Models\League;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Doctrine\Common\Cache\Psr6\InvalidArgument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;

class TeamController extends Controller
{
    private SupportCollection $competitors;
    private int $totalCompetitors = 0;
    private int $totalTeamsAmount = 0;
    private SupportCollection $teams;
    private SupportCollection $teamsWithPlayers;
    
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return view('admin.teams.index', [
            'teams' => Team::select('teams.*')->join('seasons', 'teams.season_id', 'seasons.id')
                ->orderBy('seasons.start_year')
                ->orderBy('teams.name')
                ->paginate(10),
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

    public function initiateTeamsBuilder(): View
    {    
            return view('admin/teams/team-builder', [
                'seasons' => $this->getUpToDateSeasons('asc'),
            ]);
    }

    public function validateTeamsBuilder(ValidateTeamBuilderRequest $request): View
    {
        $playersPerTeam = $request->safe()->playersPerTeam;
        $this->getCompetitors()
        ->countCompetitors()
        ->countTotalTeams($playersPerTeam)
        ->buildTeamsFromAToZ()
        ->addPlayersToTeams($playersPerTeam);

        return view('admin/teams/team-builder', [
            'seasons' => $this->getUpToDateSeasons('asc'),
            'selectedSeason' => Season::findOrFail($request->season_id),
            'leagueLevel' => LeagueLevel::cases(),
            'leagueCategory' => LeagueCategory::cases(),
            'playersPerTeam' => $playersPerTeam,
            'teamsWithPlayers' => $this->teamsWithPlayers ,
        ]);
    }

    /**
     * Return Seasons in the future, starting from this year.
     *
     * @return Season
     */
    public function getUpToDateSeasons(string $sorting_order = 'asc'): Collection
    {
        if ($sorting_order !== 'asc' & $sorting_order !== 'desc') {
            throw new InvalidArgument('This function only accepts those 2 argumments : \'asc\' or \'desc\'');
        }
        $this_year = Carbon::today()->format('Y');

        return Season::where('end_year', '>=', $this_year)->orderBy('end_year', $sorting_order)->get();
    }


    /**
     * Save in bulk the teams and associate the desired amount of players of player to each of them.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function saveTeams(Request $request): RedirectResponse
    {
        $seasonId = $request->season_id;

        // Looping for each team
        foreach($request->teams as $teamName => $data) {
            $team = new Team([
                'name' => $teamName,
            ]);
            $team->season()->associate($seasonId);
            
            // Add the captain if any
            if (isset($data['captain_id'])) {
                $team->captain()->associate($data['captain_id']);
            }

            $team->save();

            // Add the players
            foreach($data['players_id'] as $player_id) {
                $team->users()->attach($player_id);
            }

            // FindOrCreate League and add it
            $level = $data['level_id'];
            $category = $data['category_id'];
            $division = $data['division'];
            $league = League::firstOrCreate([
                'level' => $level,
                'category' => $category,
                'division' => $division,
                'season_id' => $seasonId,
            ]);

            $team->league()->associate($league->id)->save();


        }

        return redirect()->route('teams.index')->with('success', sprintf('New teams for the season %s have been created.', Season::find($seasonId)->name));
    }

    /**
     * Get competitors ordered by ranking, then by last name, both descending.
     *
     * @return self
     */
    private function getCompetitors(): self
    {
        $this->competitors = User::where('is_competitor', '=', true)
            ->orderby('force_index', 'asc')
            ->orderby('last_name', 'asc')
            ->orderby('first_name', 'asc')
            ->get();

        return $this;
    }

    /**
     * Return the amount of players that are competitors.
     *
     * @return self
     */
    protected function countCompetitors(): self
    {
        $this->totalCompetitors = $this->competitors->count();
        
        return $this;
    }

    /**
     * Return the amound of teams based on a specified amount of players per team.
     *
     * @param integer $playersPerTeam
     * @return self
     */
    protected function countTotalTeams(int $playersPerTeam = 0): self
    {

        if ($playersPerTeam < 5) {
            throw new InvalidArgument('A team must have a core of minimum 5 players.');
        }

        $this->totalTeamsAmount = intdiv($this->totalCompetitors, $playersPerTeam);

        return $this;
    }

    /**
     * Returns a collection of teams names from A to Z
     *
     * @param integer $totalTeamsAmount
     * @return self 
     */
    private function buildTeamsFromAToZ(): self
    {
        $teams = collect();
            
        for ($i = 0; $i < $this->totalTeamsAmount; $i++) {
            $teams->push(TeamName::cases()[$i]->name);
        }

        $this->teams = $teams;

        return $this;
    }

    /**
     * Add competitors to each teams
     *
     * @param SupportCollection $competitors
     * @param integer $playersPerTeam
     * @return self
     */
    private function addPlayersToTeams(int $playersPerTeam = 5): self
    {

        $this->teamsWithPlayers = collect();

        foreach($this->teams as $team){
            $players = collect();
            for ($i = 0; $i < $playersPerTeam; $i++) {
                $players->push($this->competitors->shift());
                
            }
            $this->teamsWithPlayers->put($team, $players);
        }

        return $this;
    }
}
