<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Shared\Enums\TableStateEnum;
use Illuminate\Support\Facades\Schema;

pest()->group('club', 'rooms');

it('ne porte plus de colonne total_tables', function (): void {
    // Elle était écrite par le service et le wizard, relue par personne, et
    // avait dérivé : une salle stockait 5 en tenant 7 tables.
    expect(Schema::hasColumn('rooms', 'total_tables'))->toBeFalse();
});

it('ne porte plus de compteur de tables jouables', function (): void {
    // Rien ne le tenait à jour : l'assistant tournoi annonçait 9 tables dans
    // une salle qui en avait 6. Les tables se comptent là où elles sont.
    expect(Schema::hasColumn('rooms', 'total_playable_tables'))->toBeFalse();
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

it('compte les tables jouables en direct', function (): void {
    $room = Room::factory()->create();
    Table::factory()->count(2)->for($room)->create(['state' => TableStateEnum::GOOD]);
    Table::factory()->for($room)->create(['state' => TableStateEnum::OUT_OF_SERVICE]);

    expect($room->fresh()->playableTablesCount())->toBe(2);
});
