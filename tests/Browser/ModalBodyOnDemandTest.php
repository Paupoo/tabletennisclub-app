<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
 * A closed modal used to ship its whole body on every render — the member list
 * carried 70 kB of dialogs nobody had opened, one <select> of it holding every
 * event in the club. `<x-app-modal :open>` holds the body back until the
 * property that opens the dialog is true.
 *
 * The body has to arrive when the modal is opened for real, through the button
 * a reader would press — that is the half of the change that can break.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    makeActiveSeason();
    $this->actingAs($this->admin);
});

it('keeps a closed modal out of the page and brings its body back on opening', function (): void {
    User::factory()->create(['first_name' => 'Cible', 'last_name' => 'Anonymisable']);

    $page = visit(route('admin.users.index'))->resize(1440, 1000);

    $page->assertDontSee('Tapez ANONYMIZE pour confirmer');

    $page->click('tr:has-text("Anonymisable") [data-row-menu-trigger]')
        ->click('tr:has-text("Anonymisable") >> text=Anonymiser (RGPD)')
        ->assertSee('Tapez ANONYMIZE pour confirmer');
});
