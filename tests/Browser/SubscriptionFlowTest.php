<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

/*
 * Dans ce plugin, `waitForText()` est un alias de `assertSee()` : il photographie
 * le DOM et ne réessaie pas. Aucune assertion de cette API n'attend. Tout ce qui
 * suit une frappe dans un champ `wire:model.live.debounce.300ms`, ou l'ouverture
 * d'un tiroir, doit donc passer par `wait()` — le seul primitif d'attente réel.
 * Constaté quand la version « liste des membres » de ce motif a lâché en CI.
 */

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('registrations page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.registrations'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Affiliations');
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
        ->wait(1)
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
