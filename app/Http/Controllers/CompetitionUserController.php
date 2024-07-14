<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompetitionUserRequest;
use App\Http\Requests\UpdateCompetitionUserRequest;
use App\Models\Competition;
use App\Models\CompetitionUser;
use App\Models\User;

class CompetitionUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompetitionUserRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(CompetitionUser $competitionUser)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CompetitionUser $competitionUser)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompetitionUserRequest $request, CompetitionUser $competitionUser)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CompetitionUser $competitionUser)
    {
        //
    }

    public function subscribeToCompetition(User $user, Competition $competition)
    {
        $user = User::find($user);
        $competition = Competition::($competition)

        $user->competition()->attach($competition);
    }

    public function unsubscribeToCompetition()
    {

    }

    public function selectPlayerForCompetition()
    {

    }

    public function unselectPlayerForCompetition()
    {

    }

    public function searchForAvailablePlayers()
    {

    }

    public function markSelectedPlayerPresentToCompetition()
    {

    }
    public function markSelectedPlayerAbsentFromCompetition()
    {

    }
}
