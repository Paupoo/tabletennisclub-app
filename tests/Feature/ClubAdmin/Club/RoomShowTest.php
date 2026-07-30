<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TableStateEnum;
use Livewire\Livewire;

/**
 * Remplace TableIndexTest : le parc de tables se consulte désormais depuis la
 * salle qui le contient, il n'existe plus de liste de tables autonome.
 */
describe('RoomShow Livewire Component', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->isAdmin()->create();
    });

    test('la fiche ne montre que les tables de sa salle', function (): void {
        $room = Room::factory()->create();
        $other = Room::factory()->create();

        Table::factory()->for($room)->create(['name' => 'Table 1']);
        Table::factory()->for($room)->create(['name' => 'Table 2']);
        Table::factory()->for($other)->create(['name' => 'Table 3']);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.rooms.show', ['room' => $room]);

        $names = $component->viewData('tables')->pluck('name');

        expect($names)->toHaveCount(2)
            ->and($names)->toContain('Table 1', 'Table 2')
            ->and($names)->not->toContain('Table 3');
    });

    test('une salle sans table rend quand même la page', function (): void {
        $room = Room::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.rooms.show', ['room' => $room])
            ->assertOk()
            ->assertSee(__('No table in this room yet'));
    });

    test('la fiche affiche les infos d accès, invisibles jusqu ici', function (): void {
        // building_name, floor et access_description étaient saisis dans le
        // formulaire salle et affichés nulle part.
        $room = Room::factory()->create([
            'building_name' => 'Centre Sportif du Blocry',
            'floor' => '-1',
            'access_description' => 'Entrée par le parking arrière.',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.rooms.show', ['room' => $room])
            ->assertSee('Centre Sportif du Blocry')
            ->assertSee('Entrée par le parking arrière.');
    });

    test('l état de la table est rendu via son libellé traduit', function (): void {
        $room = Room::factory()->create();
        Table::factory()->for($room)->create(['state' => TableStateEnum::OUT_OF_SERVICE]);

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.rooms.show', ['room' => $room])
            ->assertSee(TableStateEnum::OUT_OF_SERVICE->getLabel());
    });

    test('un admin peut délier une table de sa salle', function (): void {
        $room = Room::factory()->create();
        $table = Table::factory()->for($room)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.rooms.show', ['room' => $room])
            ->call('confirmUnlink', $table->id)
            ->assertSet('unlinkModal', true)
            ->call('unlink');

        expect($table->fresh()->room_id)->toBeNull();
    });

    test('un admin peut supprimer une table', function (): void {
        $room = Room::factory()->create();
        $table = Table::factory()->for($room)->create();

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.rooms.show', ['room' => $room])
            ->call('confirmDelete', $table->id)
            ->assertSet('deleteModal', true)
            ->call('delete');

        expect(Table::find($table->id))->toBeNull();
    });

    test('un membre simple n atteint pas la fiche', function (): void {
        $room = Room::factory()->create();
        $member = User::factory()->create();

        $this->actingAs($member)
            ->get(route('admin.rooms.show', $room))
            ->assertForbidden();
    });
})->group('room', 'club-admin');
