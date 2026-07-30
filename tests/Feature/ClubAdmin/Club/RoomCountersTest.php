<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Competitions\Tournament\Services\TournamentTableService;
use App\Domains\Shared\Enums\TableStateEnum;
use Illuminate\Support\Facades\Schema;

pest()->group('club', 'rooms');

it('ne porte plus de colonne total_tables', function (): void {
    // Elle était écrite par le service et le wizard, relue par personne, et
    // avait dérivé : une salle stockait 5 en tenant 7 tables.
    expect(Schema::hasColumn('rooms', 'total_tables'))->toBeFalse();
});

it('garde total_playable_tables, qui a des lecteurs', function (): void {
    expect(Schema::hasColumn('rooms', 'total_playable_tables'))->toBeTrue();
});

it('garde total_tables sur le pivot room_tournament, qui est une autre colonne', function (): void {
    expect(Schema::hasColumn('room_tournament', 'total_tables'))->toBeTrue();
});

it('compte les tables en direct, sans pouvoir dériver', function (): void {
    $room = Room::factory()->create();
    Table::factory()->count(3)->for($room)->create();

    expect(Room::withCount('tables')->find($room->id)->tables_count)->toBe(3);

    Table::factory()->for($room)->create();

    expect(Room::withCount('tables')->find($room->id)->tables_count)->toBe(4);
});

it('met encore à jour le compteur de tables jouables', function (): void {
    $room = Room::factory()->create();
    Table::factory()->count(2)->for($room)->create(['state' => TableStateEnum::GOOD]);
    Table::factory()->for($room)->create(['state' => TableStateEnum::OUT_OF_SERVICE]);

    app(TournamentTableService::class)->updateTablesCount($room);

    expect($room->fresh()->total_playable_tables)->toBe(2);
});
