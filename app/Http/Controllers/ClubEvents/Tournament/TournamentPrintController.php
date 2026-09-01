<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Tournament;

use App\Actions\Tournament\GenerateTournamentQR;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

/**
 * The two sheets a tournament needs on paper.
 *
 * Pages of their own and not a print stylesheet on the control room. The
 * control room carries a sidebar, five tabs, two drawers and three modals, all
 * of them in the DOM at once; "hide all that for the printer" is a rule nobody
 * keeps true as the page grows. These have nothing to hide, so they can be laid
 * out for A4 in black ink.
 *
 * Two sheets and not one because they are read by different people in different
 * places. The draw goes on a wall and is read standing up, from two metres, by
 * several people at once — everything added to it is paid for in the size of
 * the names. The match sheets are cut up and handed to each pool, and a pool
 * holding its own sheet needs the order of play and somewhere to write.
 */
class TournamentPrintController extends Controller
{
    /**
     * The match sheets, one cut-out card per pool.
     */
    public function matchSheets(Tournament $tournament): View
    {
        return view('print.tournament.match-sheets', [
            'tournament' => $tournament,
            'pools' => $this->pools($tournament),
        ]);
    }

    /**
     * The draw, with the QR that opens the live page.
     */
    public function poolsPoster(Tournament $tournament, GenerateTournamentQR $qr): View
    {
        $liveUrl = route('admin.tournaments.live', $tournament);

        return view('print.tournament.pools', [
            'tournament' => $tournament->load('rooms'),
            'liveUrl' => $liveUrl,
            'qrDataUri' => $qr($liveUrl),
            'pools' => $this->pools($tournament),
        ]);
    }

    /**
     * The pools with everything either sheet reads off them.
     *
     * @return Collection<int, Pool>
     */
    private function pools(Tournament $tournament): Collection
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
        ]);

        return $tournament->pools;
    }
}
