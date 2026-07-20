<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Services\TournamentTableService;
use App\Domains\Shared\Enums\TableStateEnum;

pest()->group('club', 'tables');

it('exclut les tables hors service du comptage des tables jouables', function (): void {
    $room = Room::factory()->create();

    Table::factory()->count(3)->create([
        'room_id' => $room->id,
        'state' => TableStateEnum::GOOD,
    ]);
    Table::factory()->create([
        'room_id' => $room->id,
        'state' => TableStateEnum::OUT_OF_SERVICE,
    ]);

    app(TournamentTableService::class)->updateTablesCount($room->fresh());

    expect($room->fresh()->total_playable_tables)->toBe(3);
});

it('compte une table à réparer comme jouable', function (): void {
    // « À réparer » signale un entretien à prévoir, pas une table retirée du jeu.
    $room = Room::factory()->create();

    Table::factory()->create(['room_id' => $room->id, 'state' => TableStateEnum::GOOD]);
    Table::factory()->create(['room_id' => $room->id, 'state' => TableStateEnum::NEEDS_REPAIR]);

    app(TournamentTableService::class)->updateTablesCount($room->fresh());

    expect($room->fresh()->total_playable_tables)->toBe(2);
});

it('ne lie jamais une table hors service à un tournoi', function (): void {
    $room = Room::factory()->create();

    $good = Table::factory()->create([
        'room_id' => $room->id,
        'state' => TableStateEnum::GOOD,
    ]);
    $broken = Table::factory()->create([
        'room_id' => $room->id,
        'state' => TableStateEnum::OUT_OF_SERVICE,
    ]);

    $tournament = Tournament::factory()->create();
    $tournament->rooms()->attach($room->id);

    app(TournamentTableService::class)->linkAvailableTables($tournament);

    $linked = $tournament->fresh()->tables->pluck('id');

    expect($linked)->toContain($good->id)
        ->and($linked)->not->toContain($broken->id);
});

it('caste state en enum', function (): void {
    $table = Table::factory()->create(['state' => TableStateEnum::GOOD]);

    expect($table->fresh()->state)->toBeInstanceOf(TableStateEnum::class);
});

it('expose un libellé traduit pour chaque état', function (): void {
    foreach (TableStateEnum::cases() as $state) {
        expect($state->getLabel())->toBeString()->not->toBeEmpty();
    }
});
