<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Club;

use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where the QR code stuck on a table leads.
 *
 * The sticker names the table and nothing else, so it never goes out of date:
 * what a scan opens is decided now. While a tournament using the table runs,
 * the score of the match on it; otherwise the table's sheet for whoever looks
 * after the tables, and for any other member the table and a way to tell the
 * club something is wrong with it.
 */
class TableScanController extends Controller
{
    public function __invoke(Request $request, Table $table): RedirectResponse|View
    {
        $tournament = Tournament::query()
            ->where('status', TournamentStatusEnum::PENDING)
            ->whereHas('tables', fn ($query) => $query->where('tables.id', $table->id))
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($tournament instanceof Tournament) {
            return redirect()->route('tournament.table.score', [$tournament, $table]);
        }

        if ($request->user()?->can('update', $table)) {
            return redirect()->route('admin.tables.edit', $table);
        }

        return view('public.table.show', [
            'table' => $table->load('room'),
            'clubEmail' => Club::ourClub()->value('email_contact'),
        ]);
    }
}
