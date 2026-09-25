<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Ce qu'il faut recopier dans sa banque tient dans une modale, pas dans la ligne.
 *
 * La communication SEPA fait jusqu'à 140 caractères et n'a qu'un usage : être
 * copiée. Elle vivait dans la cellule d'action, en `max-w-xs truncate` — une
 * quarantaine de caractères visibles, le reste dans une infobulle `title` que
 * personne n'atteint au doigt. Un texte qu'on ne peut pas copier en entier ne
 * sert à rien.
 *
 * L'IBAN de destination y était répété alors que le tableau a déjà sa colonne.
 */
function refundAwaitingTransfer(): array
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 200,
    ]);

    return [$treasurer, (new RequestSubscriptionRefundAction)($subscription, 137.50, 'Trop-perçu')];
}

it('keeps the transfer instructions out of the row until they are asked for', function (): void {
    [$treasurer, $refund] = refundAwaitingTransfer();

    $screen = Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'to_refund');

    // « trop-percu » sans cédille n'existe que dans la communication SEPA :
    // l'onglet et la carte écrivent « Trop-perçus ».
    $screen->assertDontSee('trop-percu');

    $screen->call('openRefundInstructions', $refund->id)
        ->assertSet('refundInstructionsModal', true)
        ->assertSee('trop-percu')
        ->assertSee('BE68539007547034')
        ->assertSee('137,50 €')
        // Blade n'exécute pas ses directives dans l'attribut d'une balise de
        // composant : un `@click="…@js($value)"` arrivait tel quel dans la page,
        // et les trois boutons « Copier » ne faisaient rien.
        ->assertDontSee('@js(');
})->group('payments', 'refund');

/**
 * Entre le virement et son rapprochement, l'écran doit savoir où il en est.
 *
 * Un remboursement ne quitte l'onglet qu'une fois **rapproché**, donc après
 * l'import du relevé — plusieurs semaines plus tard. Entre les deux, la liste
 * mélangeait sans les distinguer « pas encore viré » et « viré, en attente du
 * relevé ». Sur cinq lignes on s'en souvient ; sur trente, c'est un virement
 * en double.
 */
it('remembers that the transfer has been made', function (): void {
    [$treasurer, $refund] = refundAwaitingTransfer();

    $screen = Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'to_refund');

    $screen->assertDontSee(__('Wired on :date', ['date' => now()->format('d/m/Y')]));

    $screen->call('openRefundInstructions', $refund->id)
        ->call('markRefundAsWired')
        ->assertSet('refundInstructionsModal', false)
        ->assertSee(__('Wired on :date', ['date' => now()->format('d/m/Y')]));

    expect($refund->fresh()->refund_wired_at)->not->toBeNull();
})->group('payments', 'refund');
