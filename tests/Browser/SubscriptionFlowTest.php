<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('registrations page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.registrations'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Inscriptions');
});

it('pending subscription appears in registrations list', function (): void {
    $user = User::factory()->create(['first_name' => 'Marie', 'last_name' => 'Dupont']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);

    $this->actingAs($this->admin);

    visit(route('admin.users.registrations'))
        ->assertSee('Marie')
        ->assertSee('Dupont')
        ->assertSee('À traiter');
});

it('filter drawer opens when clicking Filtres', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.registrations'))
        ->click('Filtres')
        ->waitForText('Statut')
        ->assertSee('Statut');
});

it('subscription row shows status badge and action button', function (): void {
    $user = User::factory()->create(['first_name' => 'Henri', 'last_name' => 'Devos']);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'season_id' => $this->season->id,
        'status' => 'paid',
    ]);

    $this->actingAs($this->admin);

    visit(route('admin.users.registrations'))
        ->assertSee('Henri Devos')
        ->assertSee('Payé')
        ->assertSee('Détails')
        ->assertNoJavaScriptErrors();
});
