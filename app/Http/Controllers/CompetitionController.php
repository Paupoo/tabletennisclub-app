<?php

namespace App\Http\Controllers;

use App\Classes\HtmlFactory;
use App\Models\Competition;
use Illuminate\Http\Request;

class CompetitionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('admin.competitions.index', [
            'competitions' => Competition::orderBy('id')->paginate(10),
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
            'visited_team' => ['string','required'],
            'visiting_team' => ['string','required','different:visited_team'],
        ]); 

        Competition::create([
            'total_players' => $request->total_players,
            'competition_date' => $request->competition_date,
            'address' => $request->competition_address,
            'week_number' => $request->competition_week_number,
            'team_visited' => $request->visited_team,
            'team_visiting' => $request->visiting_team,
        ]);

        return redirect()->route('competitions.index')->with('success', 'The match ' . $request->visited_team . ' - ' . $request->visited_team . ' has been added.');
   
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
