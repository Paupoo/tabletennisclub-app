<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Tournament;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentHallScreenService;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The television in the hall, opened with the signed link the committee copies
 * from the tournament page. No sign-in: a cast screen has no keyboard.
 */
final class TournamentHallScreenController extends Controller
{
    public function __invoke(Tournament $tournament, TournamentHallScreenService $screen): View
    {
        return view('public.tournament.hall-screen', $screen->for($tournament));
    }
}
