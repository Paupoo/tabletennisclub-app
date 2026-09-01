<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Tournament;

use App\Actions\Tournament\GenerateTournamentQR;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The sheet that goes on the wall.
 *
 * A page rather than a print stylesheet on the control room. The control room
 * carries a sidebar, five tabs, two drawers and three modals, all of them in
 * the DOM at once; hiding all that for the printer is a rule nobody can keep
 * true as the page grows. A page of its own has nothing to hide.
 *
 * One sheet and not two: the QR that opens the live page and the draw belong on
 * the same wall, and a committee that has to remember to print twice will
 * print once.
 */
class TournamentPrintController extends Controller
{
    public function __invoke(Tournament $tournament, GenerateTournamentQR $qr): View
    {
        $tournament->load([
            'pools' => fn ($query) => $query->orderBy('name'),
            'pools.users',
            'pools.pairs.player1',
            'pools.pairs.player2',
            'pools.tournamentmatches' => fn ($query) => $query->orderBy('match_order'),
            'pools.tournamentmatches.player1',
            'pools.tournamentmatches.player2',
            'pools.tournamentmatches.pair1.player1',
            'pools.tournamentmatches.pair1.player2',
            'pools.tournamentmatches.pair2.player1',
            'pools.tournamentmatches.pair2.player2',
            'rooms',
        ]);

        $liveUrl = route('admin.tournaments.live', $tournament);

        return view('print.tournament-sheet', [
            'tournament' => $tournament,
            'liveUrl' => $liveUrl,
            'qrDataUri' => $qr($liveUrl),
            'pools' => $tournament->pools,
        ]);
    }
}
