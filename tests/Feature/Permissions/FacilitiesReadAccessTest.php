<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\KeyRing;
use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

pest()->group('facilities', 'permissions');

/*
| The club's rooms, tables and key rings, read by the committee.
|
| Who holds a key to the hall is exactly what every committee member should
| know. The screens answered to the management rights alone; they now open at
| `facilities.view`, and handing over a key or editing a table stays with the
| facilities délégation.
*/

beforeEach(function (): void {
    $this->room = Room::factory()->create(['name' => 'Salle Blocry']);
    $this->table = Table::factory()->create(['room_id' => $this->room->id]);
    $this->holder = User::factory()->create(['last_name' => 'Portier']);
    $this->keyRing = KeyRing::factory()->heldBy($this->holder)->create();

    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->delegate = User::factory()->withRole(Role::FACILITIES)->create();
});

it('opens the rooms and the key rings to the committee', function (): void {
    $this->actingAs($this->reader)->get(route('admin.rooms.index'))->assertOk();
    $this->actingAs($this->reader)->get(route('admin.rooms.show', $this->room))->assertOk();
    $this->actingAs($this->reader)->get(route('admin.key-rings.index'))->assertOk();
});

it('keeps the forms with the facilities délégation', function (): void {
    $this->actingAs($this->reader)->get(route('admin.rooms.create'))->assertForbidden();
    $this->actingAs($this->reader)->get(route('admin.rooms.edit', $this->room))->assertForbidden();
    $this->actingAs($this->reader)->get(route('admin.tables.edit', $this->table))->assertForbidden();
});

it('keeps a plain member out', function (): void {
    $this->actingAs(User::factory()->create())->get(route('admin.key-rings.index'))->assertForbidden();
});

it('shows a room and its tables to a reader without a way to change them', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.rooms.show', ['room' => $this->room])
        ->assertSee($this->table->name)
        ->assertDontSee(route('admin.tables.edit', $this->table))
        ->assertDontSee(route('admin.rooms.edit', $this->room))
        ->assertDontSee(route('admin.tables.create'));
});

it('lists the rooms for a reader without a way to change them', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.rooms.index')
        ->assertSee('Salle Blocry')
        ->assertDontSee('confirmDeleteRoom(' . $this->room->id . ')')
        ->assertDontSee(route('admin.rooms.create'));
});

it('tells a reader who holds which key, without a way to hand one over', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.key-rings.index')
        ->assertSee('Portier')
        ->assertDontSee('openCreate')
        ->assertDontSee('openMove(' . $this->keyRing->id . ')')
        ->assertDontSee('openRetire(' . $this->keyRing->id . ')');
});

it('refuses a reader every write on the rooms', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.rooms.index')
        ->call('confirmDeleteRoom', $this->room->id)
        ->assertForbidden();

    expect(Room::find($this->room->id))->not->toBeNull();
});
