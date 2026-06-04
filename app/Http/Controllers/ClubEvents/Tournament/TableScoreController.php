<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Tournament;

use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Competitions\Tournament\Models\TableTournament;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TableScoreController extends Controller
{
    public function show(Tournament $tournament, Table $table): View
    {
        $pivot = TableTournament::where('tournament_id', $tournament->id)
            ->where('table_id', $table->id)
            ->with(['currentMatch.player1', 'currentMatch.player2', 'currentMatch.sets', 'currentMatch.pool'])
            ->first();

        $match = ($pivot && ! $pivot->is_table_free) ? $pivot->currentMatch : null;

        return view('public.tournament.table-score', compact('tournament', 'table', 'match', 'pivot'));
    }

    public function submit(Tournament $tournament, Table $table): RedirectResponse
    {
        // Score submission is handled via the Livewire component on the same page.
        return redirect()->route('tournament.table.score', [$tournament, $table]);
    }
}
