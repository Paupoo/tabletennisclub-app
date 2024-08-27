<?php

namespace App\Http\Controllers;

use App\Enums\LeagueCategory;
use App\Http\Requests\StoreInterclubRequest;
use App\Models\Club;
use App\Models\Interclub;
use App\Models\League;
use App\Models\Room;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InterclubController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //  
        $interclubs = Interclub::orderBy('start_date_time', 'asc')->paginate(10);
        
        return view('admin.interclubs.index', [
            'interclubs' => $interclubs,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $this->authorize('create', Interclub::class);
        $club = Club::OurClub()->select('id')->first();
        $otherClubs = Club::OtherClubs()->orderBy('name')->get();
        $user = Auth::user();
        $teams = ($user->is_admin || $user->is_comittee_member) 
            ? $teams = Team::where('club_id', $club->id)->get()
            : $teams = Team::where('captain_id', $user->id)->get();
        $rooms = Room::select('id', 'name')
            ->where('capacity_for_interclubs', '>', 0)
            ->get();
           
        
        return view('admin.interclubs.create', [
            'otherClubs' => $otherClubs,
            'rooms' => $rooms,
            'teams' => $teams,
            'interclubTypes' => collect(LeagueCategory::cases()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInterclubRequest $request)
    {
        $validated = $request->validated();
        
        // Instanciating elements
        $clubTeam = Team::find($validated['team_id']);
        $season = Season::find($clubTeam->season->id);
        $league = League::find($clubTeam->league->id);
        $oppositeClub = Club::find($validated['opposite_club_id']);

        // Deal with other club's team
        $oppositeTeam = Team::firstorCreate([
            'name' => $validated['opposite_team_name'],
            'club_id' => $oppositeClub->id,
            'season_id' => $season->id,
            'league_id' => $league->id,
        ]);

        $interclub = new Interclub();
        
        if(isset($validated['is_visited'])) {       // If visited
            $room = Room::find($validated['room_id']);
            $validated['address'] = sprintf('%s, %s %s',$room->street, $room->city_code, $room->city_name);
            $interclub->visitedTeam()->associate($clubTeam);
            $interclub->visitingTeam()->associate($oppositeTeam);
            $interclub->room()->associate($room);
        } else {                                    // If visiting
            $validated['address'] = sprintf('%s, %s %s',$oppositeClub->street, $oppositeClub->city_code, $oppositeClub->city_name);
            $interclub->visitedTeam()->associate($oppositeTeam);
            $interclub->visitingTeam()->associate($clubTeam);
        }
        
        
        $interclub
            ->fill($validated)
            ->setTotalPlayersPerteam($league->category)
            ->setWeekNumber($validated['start_date_time'])
            ->save();


        return redirect()->route('interclubs.index')->with('success', 'The match has been added.');
   
    }

    /**
     * Display the specified resource.
     */
    public function show(Interclub $interclub)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Interclub $interclub)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Interclub $interclub)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Interclub $interclub)
    {
        //
    }

    public function subscribe(Request $request): RedirectResponse
    {

        $subscriptions = array_keys($request->all()['subscriptions']);

        $user = Auth::user();

        $user->interclubs()->syncWithPivotValues(array_values($subscriptions), ['is_subscribed' => true]  );

        return redirect()->route('interclubs.index')->with('success', __('You have correctly subscribed.'));
    }
}
