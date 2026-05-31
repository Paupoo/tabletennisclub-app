<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    // On crée un utilisateur pour les tests
    $this->user = User::factory()->create();
});

describe('Room index tests', function () {
    // 1. Tester que la page est accessible
    it('renders the rooms index page', function () {
        $this->actingAs($this->user)
            ->get(route('admin.rooms.index')) // Ajuste le nom de la route si besoin
            ->assertStatus(200);
    });

    // 2. Tester la visibilité des boutons selon les Policies
    it('shows action buttons only if user has permission', function () {
        $room = Room::factory()->create();

        // Cas 1 : L'utilisateur n'a aucune permission
        Livewire::actingAs($this->user)
            ->test('pages::club-admin.rooms.index')
            ->assertDontSee(__('Create'))
            ->assertDontSee(__('Modify'))
            ->assertDontSee(__('Delete'));

        // Cas 2 : On simule les permissions (via un Mock ou en donnant un rôle à l'user)
        $admin = User::factory()->create(['is_admin' => true]); // Exemple

        Livewire::actingAs($admin)
            ->test('pages::club-admin.rooms.index')
            ->assertSee(__('Create'))
            ->assertSee(__('Modify'))
            ->assertSee(__('Delete'));

        $committeeMember = User::factory()->create(['is_committee_member' => true]); // Exemple

        Livewire::actingAs($committeeMember)
            ->test('pages::club-admin.rooms.index')
            ->assertSee(__('Create'))
            ->assertSee(__('Modify'))
            ->assertSee(__('Delete'));
    });

    // 3. Tester l'action de suppression
    it('can delete a room', function () {
        $room = Room::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.rooms.index')
            ->call('delete', $room->id)
            ->assertHasNoErrors();

        expect(Room::where('id', $room->id)->exists())->toBeFalse();
    });

    // 4. Tester que la suppression est bloquée si la salle a des tables liées
    it('cannot delete a room that has linked tables', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $room = Room::factory()->create();
        Table::factory()->create(['room_id' => $room->id]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.rooms.index')
            ->call('delete', $room->id)
            ->assertHasNoErrors();

        expect(Room::where('id', $room->id)->exists())->toBeTrue();
    });

    // 5. Tester la sécurité : un user lambda ne peut pas appeler 'delete'
    it('prevents unauthorized users from deleting a room', function () {
        $room = Room::factory()->create();

        Livewire::actingAs($this->user)
            ->test('pages::club-admin.rooms.index')
            ->call('delete', $room->id)
            ->assertStatus(403); // Ou assertForbidden()

        expect(Room::where('id', $room->id)->exists())->toBeTrue();
    });
})->group('club-admin', 'room');
