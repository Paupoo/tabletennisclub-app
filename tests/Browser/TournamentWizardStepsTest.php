<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\TournamentStatusEnum;

/*
 * A doubles tournament walks from its registrations to its pairs, then to the
 * launch — which points back to the pairs while a player has no partner.
 */
it('walks a doubles tournament from the registrations to the pairs, then to the launch', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $tournament = paymentTournament([
        'status' => TournamentStatusEnum::SETUP,
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'price' => 0,
    ]);
    foreach (['Anna', 'Ben', 'Odd'] as $name) {
        $tournament->users()->attach(User::factory()->create(['first_name' => $name, 'last_name' => 'Joueur'])->id, ['registration_status' => 'registered']);
    }

    $this->actingAs($admin);

    visit(route('admin.tournaments.wizard.edit', ['tournament' => $tournament, 'step' => 5]))
        ->resize(1440, 900)
        ->assertNoJavaScriptErrors()
        ->assertSee('À pointer')
        ->assertDontSee('Composition des paires')
        ->click('[data-step="pairs"]')
        ->wait(0.5)
        ->assertSee('Composition des paires')
        ->click('[data-step="6"]')
        ->wait(0.5)
        ->assertSee('3 joueurs n’ont pas encore de partenaire')
        ->click('[data-bench-reminder] button')
        ->wait(0.5)
        ->assertSee('Composition des paires');
})->group('tournament');
