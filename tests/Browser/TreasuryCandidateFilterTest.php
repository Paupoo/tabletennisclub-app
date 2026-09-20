<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

/**
 * Le filtre des candidates est en Alpine : aucun aller-retour Livewire ne le
 * traverse, donc aucun test feature ne peut le voir travailler. Une expression
 * `x-show` fautive rend une page parfaitement valide qui ne filtre rien — c'est
 * exactement l'échec silencieux qu'un test de forme laisserait passer.
 */
it('filters the reconcile candidates without a round trip', function (): void {
    $admin = User::factory()->isAdmin()->create();
    $member = User::factory()->create(['first_name' => 'Sacha', 'last_name' => 'Vanderborght']);

    $subscription = Subscription::factory()->create(['user_id' => $member->id]);
    $subscription->payments()->create([
        'reference' => 'FLT/2026/00001',
        'amount_due' => 150,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    Transaction::create([
        'date' => now(), 'amount' => 150,
        'counterparty_name' => 'MICHOTTE MICHEL',
        'free_reference' => 'affiliation', 'description' => 'VIREMENT',
    ]);
    Transaction::create([
        'date' => now(), 'amount' => 150,
        'counterparty_name' => 'DELCOURT ANNE',
        'free_reference' => 'cotisation Félix', 'description' => 'VIREMENT',
    ]);

    $this->actingAs($admin);

    $visibleCandidates = "(() => [...document.querySelectorAll('[data-candidate]')]"
        . '.filter(el => el.offsetParent !== null).length)()';

    visit(route('admin.treasury.payments'))
        ->assertNoJavaScriptErrors()
        // `[data-row-menu-primary]` existe en double — carte mobile et ligne de
        // tableau. On vise celui du tableau, seul visible en desktop : un clic
        // sur l'élément masqué ne casserait pas, il bloquerait.
        ->click('table [data-row-menu-primary]')
        ->assertSee('MICHOTTE MICHEL')
        ->assertSee('DELCOURT ANNE')
        ->assertScript($visibleCandidates, 2)
        ->type('[data-candidate-filter]', 'michotte')
        ->assertScript($visibleCandidates, 1)
        // Sans accent : le barème les ignore, la recherche doit faire pareil.
        ->type('[data-candidate-filter]', 'felix')
        ->assertScript($visibleCandidates, 1)
        ->assertSee('DELCOURT ANNE')
        ->type('[data-candidate-filter]', 'introuvable')
        ->assertScript($visibleCandidates, 0)
        ->assertSee(__('No transaction matches this search.'))
        ->assertNoJavaScriptErrors();
})->group('treasury');
