<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TableStateEnum;
use Livewire\Livewire;

describe('Table purchased_on normalization', function (): void {

    test('a blank purchased_on is stored as null instead of raising a DB error', function (): void {
        $table = Table::create([
            'name' => 1,
            'purchased_on' => '',
        ]);

        expect($table->fresh()->purchased_on)->toBeNull();
    });

    test('a null purchased_on stays null', function (): void {
        $table = Table::create([
            'name' => 2,
            'purchased_on' => null,
        ]);

        expect($table->fresh()->purchased_on)->toBeNull();
    });

    test('a valid purchased_on date is persisted', function (): void {
        $table = Table::create([
            'name' => 3,
            'purchased_on' => '2024-05-10',
        ]);

        expect($table->fresh()->purchased_on->format('Y-m-d'))->toBe('2024-05-10');
    });

    test('the factory still persists its date value', function (): void {
        $table = Table::factory()->create();

        expect($table->fresh()->purchased_on)->not->toBeNull();
    });
});

describe('Table form component', function (): void {

    test('creating a table with an empty purchase date does not 500', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $room = Room::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.tables.form')
            ->set('name', 'Table 01')
            ->set('room_id', $room->id)
            ->set('state', TableStateEnum::NEEDS_REPAIR->value)
            ->set('purchased_on', '')
            ->call('save')
            ->assertHasNoErrors();

        $table = Table::firstWhere('name', 'Table 01');

        expect($table)->not->toBeNull()
            ->and($table->purchased_on)->toBeNull()
            ->and($table->room_id)->toBe($room->id);
    });
})->group('table', 'club-admin');
