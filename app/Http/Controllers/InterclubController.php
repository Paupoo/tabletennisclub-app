<?php

namespace App\Http\Controllers;

use App\Enums\LeagueCategory;
use App\Http\Requests\StoreInterclubRequest;
use App\Models\Interclub;
use App\Models\Room;
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
        $user = Auth::user();
        $teams = ($user->is_admin || $user->is_comittee_member) 
            ? $teams = Team::all()
            : $teams = Team::where('captain_id', $user->id)->get();
        $rooms = Room::select('id', 'name')
            ->where('capacity_for_interclubs', '>', 0)
            ->get();
           
        
        return view('admin.interclubs.create', [
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

        $room = Room::find($validated['room_id']);
        if(!isset($validated['address'])) {
            $validated['address'] = sprintf('%s, %s %s',$room->street, $room->city_code, $room->city_name);
        }
        
        $interclub = new Interclub();
        
        $interclub
            ->fill($validated)
            ->setTotalPlayersPerteam($validated['league_category'])
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
