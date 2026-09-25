<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Un trop-perçu se rend au compte qui a versé, et à aucun autre.
 *
 * La règle était écrite mais appliquée à moitié : faute de compte payeur, le
 * remboursement se repliait sur l'IBAN du membre. Or un trop-perçu vient
 * souvent d'un tiers — une commune, un employeur, un tuteur — et ce repli
 * envoie l'argent à quelqu'un qui ne l'a jamais versé, sous un écran qui
 * affirme le contraire.
 *
 * Le repli reste légitime pour une cotisation annulée : là, le club rend à son
 * titulaire. C'est le motif qui décide, pas l'absence de donnée.
 */
function overpaidClaim(User $member, callable $settle): Payment
{
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 170,
    ]);

    $encashment = $subscription->payments()->create([
        'reference' => '907/0926/00001',
        'amount_due' => 170,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $settle($encashment);

    return $encashment->fresh();
}

it('refuses to open an overpayment refund when nobody knows who paid', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    // Reçu en espèces : aucune contrepartie bancaire, donc aucun compte payeur.
    $member = User::factory()->create(['iban' => 'BE68539007547034']);
    $encashment = overpaidClaim($member, fn (Payment $p) => (new AllocateTransactionAction)->credit($p, 820.0, 'cash'));

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('openRefundRequest', $encashment->id)
        ->assertSet('refundRequestIban', '')
        ->set('refundRequestAmount', 100.0)
        ->set('refundRequestReason', 'Trop-perçu')
        ->call('confirmRefundRequest')
        ->assertSet('refundRequestModal', true);

    expect(Payment::where('status', 'to_refund')->count())->toBe(0);
})->group('payments', 'overpaid');

/**
 * Quand le virement entrant est connu, c'est son compte qui est retenu — même
 * si le membre en a un autre.
 */
it('keeps the account the money came from, not the member own', function (): void {
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    $member = User::factory()->create(['iban' => 'BE68539007547034']);

    $encashment = overpaidClaim($member, function (Payment $p): void {
        $transfer = Transaction::create([
            'date' => now()->toDateString(),
            'description' => 'SUBSIDE',
            'amount' => 820.0,
            'counterparty_name' => "Commune d'Ottignies-LLN",
            'counterparty_bank_account' => 'BE62510007547061',
        ]);

        (new AllocateTransactionAction)($transfer, [$p->id => 820.0]);
    });

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('openRefundRequest', $encashment->id)
        ->assertSet('refundRequestIban', 'BE62510007547061')
        ->set('refundRequestAmount', 100.0)
        ->set('refundRequestReason', 'Trop-perçu')
        ->call('confirmRefundRequest')
        ->assertSet('refundRequestModal', false);

    expect(Payment::where('status', 'to_refund')->sole()->refund_iban)->toBe('BE62510007547061');
})->group('payments', 'overpaid');
