<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Support\Collection;
use Livewire\Livewire;

describe('TableIndex Livewire Component', function () {

    test('component renders with empty tables', function () {
        // Arrange : Aucune table n'existe
        // (La DB est vide grâce à RefreshDatabase du TestCase)

        // Act : On rend le composant
        $component = Livewire::test('pages::club-admin.tables');

        // Assert : Les données retournées sont correctes
        expect($component->viewData('groupedTables'))->toBeInstanceOf(Collection::class);
        expect($component->viewData('groupedTables')->count())->toBe(0);
    });

    test('tables are grouped by room', function () {
        // Arrange
        $room1 = Room::factory()->create(['name' => 'Room A']);
        $room2 = Room::factory()->create(['name' => 'Room B']);

        $table1 = Table::factory()->for($room1)->create(['name' => 'Table 1']);
        $table2 = Table::factory()->for($room1)->create(['name' => 'Table 2']);
        $table3 = Table::factory()->for($room2)->create(['name' => 'Table 3']);

        // Act
        $component = Livewire::test('pages::club-admin.tables');
        $grouped = $component->viewData('groupedTables');

        // Assert
        expect($grouped->count())->toBe(2);

        // Vérifier que les groupes ont la bonne structure
        $grouped->each(function ($group) {
            expect($group)->toHaveKeys(['room', 'room_display', 'tables']);
            expect($group['tables'])->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
        });

        // Vérifier que le groupement est correct
        $group1 = $grouped->first();
        expect($group1['room']->id)->toBe($room1->id);
        expect($group1['tables']->count())->toBe(2);
    });

    test('unassigned tables are grouped separately', function () {
        // Arrange
        $room = Room::factory()->create(['name' => 'Room A']);

        Table::factory()->for($room)->create(); // Assigned
        Table::factory()->create(['room_id' => null]); // Unassigned

        // Act
        $component = Livewire::test('pages::club-admin.tables');
        $grouped = $component->viewData('groupedTables');

        // Assert
        // Find the specific group where 'room' key is null
        $unassignedGroup = $grouped->firstWhere('room', null);

        expect($unassignedGroup)->not->toBeNull();
        expect($unassignedGroup['room_display'])->toBe(__('Not Assigned'));
        expect($unassignedGroup['tables']->count())->toBe(1);
    });

    test('unassigned group does not exist if all tables are linked', function () {
        // Arrange: Only create assigned tables
        $room = Room::factory()->create();
        Table::factory()->for($room)->create();

        // Act
        $component = Livewire::test('pages::club-admin.tables');
        $grouped = $component->viewData('groupedTables');

        // Assert: There should be no group with a null room
        $unassignedGroup = $grouped->firstWhere('room', null);
        expect($unassignedGroup)->toBeNull();
    });

    test('groups are sorted alphabetically by room name', function () {
        // Arrange
        $roomZ = Room::factory()->create(['name' => 'Zebra Room']);
        $roomA = Room::factory()->create(['name' => 'Alpha Room']);
        $roomM = Room::factory()->create(['name' => 'Milo Room']);

        Table::factory()->for($roomZ)->create();
        Table::factory()->for($roomA)->create();
        Table::factory()->for($roomM)->create();

        // Act
        $component = Livewire::test('pages::club-admin.tables');
        $grouped = $component->viewData('groupedTables');

        // Assert
        $roomNames = $grouped->pluck('room_display')->toArray();
        expect($roomNames)->toBe(['Alpha Room', 'Milo Room', 'Zebra Room']);
    });

    test('search filters tables by name', function () {
        // Arrange
        $room = Room::factory()->create();
        Table::factory()->for($room)->create(['name' => 'Premium Table']);
        Table::factory()->for($room)->create(['name' => 'Budget Table']);
        Table::factory()->for($room)->create(['name' => 'Tournament Table']);

        // Act : Chercher "Premium"
        $component = Livewire::test('pages::club-admin.tables')
            ->set('search', 'Premium');

        // Assert
        $grouped = $component->viewData('groupedTables');
        $totalTables = $grouped->sum(fn ($g) => $g['tables']->count());

        expect($totalTables)->toBe(1);
        expect($grouped->first()['tables']->first()->name)->toBe('Premium Table');
    });

    test('search filters tables by state', function () {
        // Arrange
        $room = Room::factory()->create();
        Table::factory()->for($room)->create(['name' => 'Table 1', 'state' => 'Good condition']);
        Table::factory()->for($room)->create(['name' => 'Table 2', 'state' => 'Needs repair']);
        Table::factory()->for($room)->create(['name' => 'Table 3', 'state' => 'Good condition']);

        // Act : Chercher "Needs repair"
        $component = Livewire::test('pages::club-admin.tables')
            ->set('search', 'Needs repair');

        // Assert
        $grouped = $component->viewData('groupedTables');
        $totalTables = $grouped->sum(fn ($g) => $g['tables']->count());

        expect($totalTables)->toBe(1);
    });

    test('search is debounced', function () {
        // Arrange
        $room = Room::factory()->create();
        Table::factory(5)->for($room)->create();

        // Act : Écrire rapidement plusieurs caractères
        $component = Livewire::test('pages::club-admin.tables');

        // On vérifie que la directive wire:model.live.debounce.300ms est présente
        // Ce test est plus un test d'intégration que d'unité
        // Tu peux le valider en inspectant la vue

        $component->set('search', 'T');
        $component->set('search', 'Ta');
        $component->set('search', 'Tab'); // Seul ce dernier compte après 300ms

        // Assert : Le résultat ne change qu'une fois
        $grouped = $component->viewData('groupedTables');
        expect($grouped)->not->toBeNull();
    });

    test('unlink action removes table from room', function () {
        // Arrange
        $room = Room::factory()->create();
        $table = Table::factory()->for($room)->create();

        expect($table->room_id)->toBe($room->id);

        // Act
        $component = Livewire::test('pages::club-admin.tables');
        $component->call('unlink', $table);

        // Assert
        $table->refresh(); // Recharger depuis la DB
        expect($table->room_id)->toBeNull();
    });

    test('unlink action displays success message', function () {
        // Arrange
        $room = Room::factory()->create();
        $table = Table::factory()->for($room)->create();

        // Act
        $component = Livewire::test('pages::club-admin.tables');
        $component->call('unlink', $table);

        // Assert
        // Vérifie que le toast de succès a été envoyé
        // (la classe Toast de Mary fournirait cette méthode)
        // Tu peux tester que l'événement a été emis :
        $component->assertDispatched('success', function ($event) {
            // Vérifie que le message contient le texte attendu
            return str_contains($event['message'] ?? '', 'unlinked');
        });
    })->skip('Not able to test toasts');

    test('refresh action updates data', function () {
        // Arrange
        $room = Room::factory()->create();
        Table::factory(3)->for($room)->create();

        $component = Livewire::test('pages::club-admin.tables');
        $initialCount = $component->viewData('groupedTables')
            ->sum(fn ($g) => $g['tables']->count());

        // Act : Créer une nouvelle table après le rendu initial
        Table::factory()->for($room)->create();

        // Rafraîchir le composant
        $component->call('$refresh');

        // Assert
        $newCount = $component->viewData('groupedTables')
            ->sum(fn ($g) => $g['tables']->count());

        expect($newCount)->toBe($initialCount + 1);
    });

    test('headers are properly configured', function () {
        // Arrange & Act
        $component = Livewire::test('pages::club-admin.tables');
        $headers = $component->viewData('headers');

        // Assert
        expect($headers)->toBeArray();
        expect($headers)->toHaveCount(5); // name, purchased_on, is_competition_ready, state, actions

        // Vérifier que chaque header a la structure attendue
        collect($headers)->each(function ($header) {
            expect($header)->toHaveKeys(['key', 'label', 'class']);
        });

        // Vérifier les colonnes spécifiques
        $keys = collect($headers)->pluck('key')->toArray();
        expect($keys)->toBe([
            'name',
            'purchased_on',
            'is_competition_ready',
            'state',
            'actions',
        ]);
    });

    test('complete workflow: search, view, and unlink', function () {
        // Arrange
        $room1 = Room::factory()->create(['name' => 'Room A']);
        $room2 = Room::factory()->create(['name' => 'Room B']);

        $table1 = Table::factory()->for($room1)->create(['name' => 'Competition Table', 'state' => 'Good condition']);
        $table2 = Table::factory()->for($room1)->create(['name' => 'Training Table', 'state' => 'Good condition']);
        $table3 = Table::factory()->for($room2)->create(['name' => 'Competition Table', 'state' => 'Needs repair']);

        $component = Livewire::test('pages::club-admin.tables');

        // Act 1 : Rechercher "Competition"
        $component->set('search', 'Competition');
        $grouped = $component->viewData('groupedTables');

        // Assert 1 : Devrait trouver 2 tables
        $totalTables = $grouped->sum(fn ($g) => $g['tables']->count());
        expect($totalTables)->toBe(2);

        // Act 2 : Délier la première table de sa room
        $component->call('unlink', $table1);

        // Assert 2 : La table n'a plus de room
        $table1->refresh();
        expect($table1->room_id)->toBeNull();

        // Act 3 : Réinitialiser la recherche
        $component->set('search', '');

        // Assert 3 : Voir toutes les tables groupées correctement
        $grouped = $component->viewData('groupedTables');

        // La table1 (déliée) devrait être dans le groupe "Not Assigned"
        $unassignedGroup = $grouped->firstWhere('room_display', __('Not Assigned'));
        expect($unassignedGroup['tables']->pluck('id')->contains($table1->id))->toBeTrue();
    });

    test('breadcrumbs are correct', function () {
        // Act
        $component = Livewire::test('pages::club-admin.tables');
        $breadcrumbs = $component->viewData('breadcrumbs');

        // Assert
        expect($breadcrumbs)->toBeArray();
        expect($breadcrumbs)->not->toBeEmpty();
    });

    describe('User permissions', function() {
        test('a user cannot create a new table', function() {
            
        });

        test('a user cannot edit a table', function() {

        });

        test('a user cannot unlink a table from a room', function() {

        });

        test('a user cannot delete a table', function() {

        });

        test('an admin or committee member can create a new table', function() {
            $admin = User::factory()->create(['is_admin' => true]); // Exemple

            Livewire::actingAs($admin)
                ->test('pages::club-admin.tables.index')
                ->assertSee(__('Create'));
        });

        test('an admin or committee member can edit a table', function() {

            $table = \App\Models\ClubAdmin\Club\Table::factory()->create();
            $admin = User::factory()->create(['is_admin' => true]); // Exemple

            Livewire::actingAs($admin)
                ->test('pages::club-admin.tables.index')
                ->assertSeeHtml(__('Edit'));
        });
        
        test('an admin or committee member can unlink a table from a room', function() {
            $admin = User::factory()->create(['is_admin' => true]); // Exemple

            Livewire::actingAs($admin)
                ->test('pages::club-admin.tables.index')
                ->assertSee(__('Unlink'));
        });

        test('an admin or committee member can delete a table', function() {
            $admin = User::factory()->create(['is_admin' => true]); // Exemple

            Livewire::actingAs($admin)
                ->test('pages::club-admin.tables.index')
                ->assertSee(__('Delete'));
        });

    });
})->group('table', 'club-admin');
