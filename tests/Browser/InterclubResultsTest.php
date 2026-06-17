<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('public results page loads without JS errors', function (): void {
    visit(route('results'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Résultats');
});

it('admin interclub results page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.results'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Résultats');
});

it('captain selection page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.captain-selection'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Sélections');
});

it('control center page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.control-center'))
        ->assertNoJavaScriptErrors();
});
