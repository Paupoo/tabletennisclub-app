<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

describe('Users admin index page', function (): void {
    it('renders without error for admins', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.index')
            ->assertOk();
    });

    it('displays users list', function (): void {
        User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Dupont']);

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.index')
            ->assertSee('Alice');
    });

    it('can search users by name', function (): void {
        User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Martin']);
        User::factory()->create(['first_name' => 'Bob', 'last_name' => 'Dupont']);

        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.index')
            ->set('search', 'Alice')
            ->assertSee('Alice')
            ->assertDontSee('Bob');
    });
});

describe('Tournaments admin index page', function (): void {
    it('renders without error for admins', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-events.tournaments.index')
            ->assertOk();
    });

    it('displays tournaments list', function (): void {
        Tournament::factory()->create(['name' => 'Open de Belgique']);

        Livewire::actingAs($this->admin)
            ->test('pages::club-events.tournaments.index')
            ->assertSee('Open de Belgique');
    });
});

describe('Rooms admin index page', function (): void {
    it('renders without error for admins', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.rooms.index')
            ->assertOk();
    });
});

describe('Seasons admin index page', function (): void {
    it('renders without error for admins', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::club-admin.seasons.index')
            ->assertOk();
    });
});
