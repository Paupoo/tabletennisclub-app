<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;

/*
 * A confirmation opens the app's own dialog, in the theme, and the action runs
 * only once it is confirmed. The browser's confirm() box is never used: a
 * Livewire test cannot tell the two apart, a browser can.
 */
it('asks in the app dialog before sending every pair back to the bench, and only then does it', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::SETUP,
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'max_users' => 8,
    ]);
    foreach ([['Anna', 'Ben'], ['Carl', 'Dora']] as [$first, $second]) {
        $a = User::factory()->create(['first_name' => $first]);
        $b = User::factory()->create(['first_name' => $second]);
        $tournament->users()->attach([$a->id, $b->id], ['registration_status' => 'registered']);
        $tournament->pairs()->create(['player1_id' => $a->id, 'player2_id' => $b->id, 'registered_by' => $admin->id]);
    }

    $this->actingAs($admin);

    $page = visit(route('admin.tournaments.wizard.edit', ['tournament' => $tournament, 'step' => 'pairs']))
        ->assertNoJavaScriptErrors()
        ->click('Tout remettre sur le banc')
        ->wait(0.5)
        ->assertSee('Défaire toutes les paires et remettre tout le monde sur le banc');

    expect($tournament->pairs()->count())->toBe(2);

    $page->click('[data-confirm-dialog] .btn-error')->wait(1);

    expect($tournament->pairs()->count())->toBe(0);
})->group('tournament');
