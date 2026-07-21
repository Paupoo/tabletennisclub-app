<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    // On crée un utilisateur pour les tests
    $this->user = User::factory()->create();
});

describe('Room index tests', function (): void {
    // 1. Tester que la page est accessible au comité
    it('renders the rooms index page for the committee', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();

        $this->actingAs($committee)
            ->get(route('admin.rooms.index'))
            ->assertStatus(200);
    });

    // 1b. Un membre lambda n'a pas accès à la page (réservée au comité)
    it('forbids a plain member from the rooms index page', function (): void {
        $this->actingAs($this->user)
            ->get(route('admin.rooms.index'))
            ->assertForbidden();
    });

    // 2. Tester la visibilité des boutons selon les Policies
    it('shows action buttons only if user has permission', function (): void {
        $room = Room::factory()->create();

        // Cas 1 : L'utilisateur n'a aucune permission
        Livewire::actingAs($this->user)
            ->test('pages::club-admin.rooms.index')
            ->assertDontSee(__('Create'))
            ->assertDontSee(__('Modify'))
            ->assertDontSee(__('Delete'));

        // Cas 2 : On simule les permissions (via un Mock ou en donnant un rôle à l'user)
        $admin = User::factory()->isAdmin()->create(); // Exemple

        Livewire::actingAs($admin)
            ->test('pages::club-admin.rooms.index')
            ->assertSee(__('Create'))
            ->assertSee(__('Modify'))
            ->assertSee(__('Delete'));

        $committeeMember = User::factory()->isCommitteeMember()->create(); // Exemple

        Livewire::actingAs($committeeMember)
            ->test('pages::club-admin.rooms.index')
            ->assertSee(__('Create'))
            ->assertSee(__('Modify'))
            ->assertSee(__('Delete'));
    });

    // 3. Tester l'action de suppression
    it('can delete a room', function (): void {
        $room = Room::factory()->create();
        $admin = User::factory()->isAdmin()->create();

        Livewire::actingAs($admin)
            ->test('pages::club-admin.rooms.index')
            ->call('delete', $room->id)
            ->assertHasNoErrors();

        expect(Room::where('id', $room->id)->exists())->toBeFalse();
    });

    // 4. Tester que la suppression est bloquée si la salle a des tables liées
    it('cannot delete a room that has linked tables', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $room = Room::factory()->create();
        Table::factory()->create(['room_id' => $room->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.rooms.index')
            ->call('delete', $room->id)
            ->assertHasNoErrors();

        expect(Room::where('id', $room->id)->exists())->toBeTrue();
    });

    // 5. Tester la sécurité : un user lambda ne peut pas appeler 'delete'
    it('prevents unauthorized users from deleting a room', function (): void {
        $room = Room::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::club-admin.rooms.index')
            ->call('delete', $room->id)
            ->assertStatus(403); // Ou assertForbidden()

        expect(Room::where('id', $room->id)->exists())->toBeTrue();
    });

    // 6. Les tables orphelines vivent hors de la grille des salles
    it('hides the unassigned section when every table has a room', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();
        Table::factory()->for(Room::factory())->create();

        $component = Livewire::actingAs($committee)
            ->test('pages::club-admin.rooms.index');

        expect($component->viewData('unassignedTables'))->toBeEmpty();
        $component->assertDontSee(__('Tables not linked to any room'));
    });

    it('lists tables with no room in a section of their own', function (): void {
        // `room_id` est nullable : sans cette section, une orpheline ne serait
        // atteignable depuis nulle part.
        $committee = User::factory()->isCommitteeMember()->create();
        Table::factory()->for(Room::factory())->create(['name' => 'Assignée']);
        Table::factory()->create(['name' => 'Orpheline', 'room_id' => null]);

        $component = Livewire::actingAs($committee)
            ->test('pages::club-admin.rooms.index');

        expect($component->viewData('unassignedTables')->pluck('name'))
            ->toContain('Orpheline')
            ->not->toContain('Assignée');

        $component->assertSee(__('Unassigned'));
    });
})->group('club-admin', 'room');
