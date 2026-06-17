<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
});

it('tournaments list page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.tournaments.index'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Tournois');
});

it('tournament wizard page loads and shows step 1', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.tournaments.wizard'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Assistant de configuration du tournoi')
        ->assertSee('Configuration');
});

it('tournament live-center loads for existing tournament', function (): void {
    $tournament = Tournament::factory()->create();
    $this->actingAs($this->admin);

    visit(route('admin.tournaments.live-center', $tournament))
        ->assertNoJavaScriptErrors();
});

it('existing tournament appears in the list', function (): void {
    Tournament::factory()->create(['name' => 'Tournoi de test unique 3AbC']);
    $this->actingAs($this->admin);

    visit(route('admin.tournaments.index'))
        ->assertSee('Tournoi de test unique 3AbC');
});
