<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompetitionUserRequest;
use App\Http\Requests\UpdateCompetitionUserRequest;
use App\Models\Competition;
use App\Models\CompetitionUser;
use App\Models\User;

class CompetitionUserController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create()
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

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CompetitionUser $competitionUser)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function markSelectedPlayerAbsentFromCompetition() {}

    public function markSelectedPlayerPresentToCompetition() {}

    public function searchForAvailablePlayers() {}

    public function selectPlayerForCompetition() {}

    /**
     * Display the specified resource.
     */
    public function show(CompetitionUser $competitionUser)
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

    public function subscribeToCompetition(User $user, Competition $competition)
    {
        $user = User::find($user);
        // $competition = Competition::($competition)

        $user->competition()->attach($competition);
    }

    public function unselectPlayerForCompetition() {}

    public function unsubscribeToCompetition() {}

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompetitionUserRequest $request, CompetitionUser $competitionUser)
    {
        //
    }
}
