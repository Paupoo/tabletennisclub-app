<?php

namespace App\Http\Controllers;

use App\Classes\HtmlFactory;
use App\Models\Competition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompetitionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //  
        $competitions = User::find(Auth::user()->id)->competitions()->orderBy('competition_date', 'asc')->paginate(10);
        
        return view('admin.competitions.index', [
            'competitions' => $competitions,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view('admin.competitions.create', [
            'competition_types' => HtmlFactory::competitionTypesInHtmlList(),
            'teams' => Team::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'total_players' => ['integer','between:3,4','required'],
            'competition_date' => ['date', 'required'],
            'competition_address' => ['string', 'required'],
            'competition_week_number' => ['integer','between:1,52'],
            'club_team' => ['integer','required'],
            'opposing_team' => ['string','required','different:visited_team'],
        ]); 

        Competition::create([
            'total_players' => $request->total_players,
            'competition_date' => $request->competition_date,
            'address' => $request->competition_address,
            'week_number' => $request->competition_week_number,
            'team_id' => $request->club_team,
            'opposing_team' => $request->opposing_team,
        ]);

        return redirect()->route('competitions.index')->with('success', 'The match has been added.');
   
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
