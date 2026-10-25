<?php

declare(strict_types=1);

use App\Actions\Tournament\GenerateTournamentQR;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

/*
 * A sticker on the edge of each table carries a QR code that never changes:
 * it names the table, not a tournament nor a room. What a scan opens is
 * decided at the time of the scan — the score of the match on the table
 * while a tournament runs, the table's sheet for whoever looks after the
 * tables, a way to report a problem for any other member.
 */

pest()->group('club', 'tables');

function scannedTable(): Table
{
    $room = Room::factory()->create(['name' => 'Demeester -1']);

    return Table::factory()->create(['name' => '12', 'room_id' => $room->id]);
}

function tournamentOnTable(Table $table, TournamentStatusEnum $status, string $startDate): Tournament
{
    $tournament = Tournament::factory()->create(['status' => $status, 'start_date' => $startDate]);
    $tournament->tables()->attach($table->id, ['is_table_free' => true]);

    return $tournament;
}

describe('scanning a table', function (): void {
    it('asks whoever is not signed in to sign in first', function (): void {
        $this->get(route('table.scan', scannedTable()))->assertRedirect(route('login'));
    });

    it('opens the score of the table while a tournament using it runs', function (): void {
        $table = scannedTable();
        $tournament = tournamentOnTable($table, TournamentStatusEnum::PENDING, today()->toDateString());

        $this->actingAs(User::factory()->create())
            ->get(route('table.scan', $table))
            ->assertRedirect(route('tournament.table.score', [$tournament, $table]));
    });

    it('takes the tournament that started last when two use the table', function (): void {
        $table = scannedTable();
        tournamentOnTable($table, TournamentStatusEnum::PENDING, today()->subDays(3)->toDateString());
        $latest = tournamentOnTable($table, TournamentStatusEnum::PENDING, today()->toDateString());

        $this->actingAs(User::factory()->create())
            ->get(route('table.scan', $table))
            ->assertRedirect(route('tournament.table.score', [$latest, $table]));
    });

    it('ignores a tournament not launched yet', function (): void {
        $table = scannedTable();
        tournamentOnTable($table, TournamentStatusEnum::SETUP, today()->toDateString());

        $this->actingAs(User::factory()->isAdmin()->create())
            ->get(route('table.scan', $table))
            ->assertRedirect(route('admin.tables.edit', $table));
    });

    it('opens the table sheet for whoever looks after the tables', function (): void {
        $table = scannedTable();

        $this->actingAs(User::factory()->isAdmin()->create())
            ->get(route('table.scan', $table))
            ->assertRedirect(route('admin.tables.edit', $table));
    });

    it('shows any other member the table and a way to report a problem to the club', function (): void {
        Club::factory()->ownClub()->create(['email_contact' => 'info@club.test']);
        $table = scannedTable();

        $this->actingAs(User::factory()->create())
            ->get(route('table.scan', $table))
            ->assertOk()
            ->assertSee('Demeester -1')
            ->assertSee(__('Report a problem'))
            ->assertSee('mailto:info@club.test', false)
            ->assertDontSee(__('Good condition'));
    });

    it('offers no report button when the club has no address', function (): void {
        Club::factory()->ownClub()->create(['email_contact' => null]);

        $this->actingAs(User::factory()->create())
            ->get(route('table.scan', scannedTable()))
            ->assertOk()
            ->assertDontSee(__('Report a problem'));
    });
});

describe('printing the stickers', function (): void {
    it('prints one sticker per table, each carrying the permanent address of its table', function (): void {
        $table = scannedTable();
        $other = Table::factory()->create(['name' => '3', 'room_id' => null]);

        $this->actingAs(User::factory()->isAdmin()->create())
            ->get(route('admin.tables.stickers'))
            ->assertOk()
            ->assertSee('data-scan-url="' . route('table.scan', $table) . '"', false)
            ->assertSee('data-scan-url="' . route('table.scan', $other) . '"', false);
    });

    it('prints the tables of one room, or one table', function (): void {
        $table = scannedTable();
        $elsewhere = Table::factory()->create(['name' => '3', 'room_id' => Room::factory()->create()->id]);
        $admin = User::factory()->isAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.tables.stickers', ['room' => $table->room_id]))
            ->assertSee(route('table.scan', $table), false)
            ->assertDontSee(route('table.scan', $elsewhere), false);

        $this->actingAs($admin)
            ->get(route('admin.tables.stickers', ['table' => $elsewhere->id]))
            ->assertSee(route('table.scan', $elsewhere), false)
            ->assertDontSee(route('table.scan', $table), false);
    });

    it('is kept for whoever looks after the tables', function (): void {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.tables.stickers'))
            ->assertForbidden();
    });
});

it('goes back to the room once a table is saved', function (): void {
    $table = scannedTable();

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->test('pages::club-admin.tables.form', ['table' => $table])
        ->call('save')
        ->assertRedirect(route('admin.rooms.show', $table->room_id));
});

it('draws the code in the club blue of the design system', function (): void {
    $dataUri = (new GenerateTournamentQR)('https://example.test/table/1', 200);
    $image = imagecreatefromstring(base64_decode(substr($dataUri, strlen('data:image/png;base64,'))));

    // The darkest pixel of the code is a module, and it is the club blue.
    $darkest = null;
    for ($x = 0; $x < imagesx($image); $x++) {
        for ($y = 0; $y < imagesy($image); $y++) {
            $rgb = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            $sum = $rgb['red'] + $rgb['green'] + $rgb['blue'];
            if ($darkest === null || $sum < $darkest['sum']) {
                $darkest = ['sum' => $sum, 'rgb' => [$rgb['red'], $rgb['green'], $rgb['blue']]];
            }
        }
    }

    expect($darkest['rgb'])->toBe([30, 64, 175]);
});
