<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Club;

use App\Actions\Tournament\GenerateTournamentQR;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The sheet of QR stickers to cut out and stick on the edge of each table.
 *
 * Every table by default; the tables of one room with `?room=`, a single one
 * with `?table=` to replace a sticker that wore out. Each sticker carries the
 * table's permanent address, so it never has to be printed again.
 */
class TableStickersController extends Controller
{
    /** Stickers on an A4 page: three across, five down, five centimetres each. */
    public const int PER_PAGE = 15;

    public function __invoke(Request $request, GenerateTournamentQR $qr): View
    {
        $tables = Table::query()
            ->with('room')
            ->when($request->integer('room') > 0, fn ($query) => $query->where('room_id', $request->integer('room')))
            ->when($request->integer('table') > 0, fn ($query) => $query->whereKey($request->integer('table')))
            ->get()
            // Par numéro : l'autocollant ne porte pas la salle, et une feuille
            // dans l'ordre se découpe et se distribue sans chercher.
            ->sort(fn (Table $a, Table $b): int => strnatcmp($a->name, $b->name))
            ->values();

        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        return view('print.tables.stickers', [
            'pages' => $tables
                ->map(fn (Table $table): array => [
                    'table' => $table,
                    'url' => route('table.scan', $table),
                    'qr' => $qr(route('table.scan', $table), 480),
                ])
                ->chunk(self::PER_PAGE),
            'isPublicAddress' => str_starts_with((string) config('app.url'), 'https://')
                && ! in_array($host, ['localhost', '127.0.0.1'], true)
                && ! str_ends_with($host, '.test'),
        ]);
    }
}
