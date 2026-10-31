<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create([
        'first_name' => 'Admin',
        'last_name' => 'Test',
    ]);
    $this->season = makeActiveSeason();
});

it('renders users index without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.index'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('displays users in the list', function (): void {
    User::factory()->create(['first_name' => 'Juliette', 'last_name' => 'Bernard']);

    $this->actingAs($this->admin);

    // Affiliated nowhere: the list opens on the current members, so the default view is dismissed.
    visit(route('admin.users.index', ['allMembers' => 1]))
        ->assertSee('Juliette')
        ->assertSee('Bernard');
});

it('search filters users via Livewire reactive update', function (): void {
    User::factory()->create(['first_name' => 'Juliette', 'last_name' => 'Bernard']);
    User::factory()->create(['first_name' => 'ZzzZzz', 'last_name' => 'ZzzZzz']);

    $this->actingAs($this->admin);

    /*
     * Ce test échouait en CI et passait en local, et `waitForText` n'y était
     * pour rien — au sens propre : dans ce plugin, `waitForText()` est un alias
     * de `assertSee()`, qui photographie le DOM sans réessayer. Aucune assertion
     * de cette API n'attend quoi que ce soit.
     *
     * Le champ est en `wire:model.live.debounce.300ms` : entre la frappe et la
     * liste filtrée il y a 300 ms plus un aller-retour serveur, pendant lesquels
     * `assertDontSee` voyait encore la ligne. Une attente fixe de 2 s a suffi
     * deux mois, puis perdu la course une fois sur un runner lent (2026-10-26).
     * On attend désormais l'effet lui-même : la page est relue jusqu'à ce que la
     * ligne ait disparu, dix secondes au plus.
     */
    $page = visit(route('admin.users.index', ['allMembers' => 1]))
        ->assertSee('Juliette')
        ->assertSee('ZzzZzz')
        ->type('input[id$="search"]', 'Juliette');

    $page->script(<<<'JS'
        (async () => {
          const deadline = Date.now() + 10000;
          while (document.body.innerText.includes('ZzzZzz') && Date.now() < deadline) {
            await new Promise(resolve => setTimeout(resolve, 50));
          }
        })()
        JS);

    $page->assertSee('Juliette')
        ->assertDontSee('ZzzZzz');
});

it('shows competitive badge for competitor users', function (): void {
    User::factory()->isCompetitor()->create(['first_name' => 'Marc', 'last_name' => 'Sportif']);

    $this->actingAs($this->admin);

    // "Competitive" translates to "Compétition" in fr_BE
    visit(route('admin.users.index'))
        ->assertSee('Marc')
        ->assertSee('Compétition');
});

it('shows recreational badge for non-competitor users', function (): void {
    // The licence is shown next to an affiliation of the running season only.
    $luc = User::factory()->isNotCompetitor()->create(['first_name' => 'Luc', 'last_name' => 'Loisir']);
    Subscription::create([
        'user_id' => $luc->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
    ]);

    $this->actingAs($this->admin);

    // "Recreational" translates to "Récréatif" in fr_BE
    visit(route('admin.users.index'))
        ->assertSee('Luc')
        ->assertSee('Récréatif');
});

it('navigates to create user form', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.users.create'))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('user edit form renders without errors', function (): void {
    $user = User::factory()->create();

    $this->actingAs($this->admin);

    visit(route('admin.users.edit', $user))
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('paid badge shows for users with paid subscription', function (): void {
    $user = User::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Payée']);
    Subscription::create([
        'user_id' => $user->id,
        'season_id' => $this->season->id,
        'status' => 'paid',
        'is_competitive' => false,
    ]);

    $this->actingAs($this->admin);

    visit(route('admin.users.index'))
        ->assertSee('Sophie')
        ->assertSee('Payé');
});
