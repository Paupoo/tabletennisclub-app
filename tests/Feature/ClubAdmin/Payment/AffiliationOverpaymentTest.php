<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
| Le trop-perçu d'une affiliation se compte sur l'affiliation entière.
|
| Un prix qui baisse après paiement laisse de l'argent en trop sans qu'aucune
| ligne ne le porte : la ligne payée a encaissé exactement ce qu'elle
| réclamait. L'onglet lisait ligne par ligne et ne le voyait pas. Pour une
| affiliation, l'excédent se calcule sur l'ensemble — reçu moins dû moins
| remboursements engagés — et s'affiche sur la dernière ligne créditée, celle
| d'où partirait le remboursement.
*/

/** @param  list<array{due: float, paid: float, status: string}>  $lines */
function affiliationWithLines(float $affiliationDue, array $lines): Subscription
{
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => $affiliationDue,
    ]);

    foreach ($lines as $index => $line) {
        $subscription->payments()->create([
            'reference' => '779/0926/0000' . $index,
            'amount_due' => $line['due'],
            'amount_paid' => $line['paid'],
            'status' => $line['status'],
            'payment_method' => 'Wire',
        ]);
    }

    return $subscription;
}

function overpaidTab(): Testable
{
    $treasurer = User::factory()->create();
    $treasurer->assignRole(Role::TREASURY->value);

    return Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'overpaid');
}

it('lists what a price cut left in excess on an affiliation paid in full', function (): void {
    // Payée 125 €, puis ramenée à 101,83 € : 23,17 € que plus aucune ligne ne réclame.
    affiliationWithLines(101.83, [['due' => 125, 'paid' => 125, 'status' => 'paid']]);

    overpaidTab()
        ->assertSee('779/0926/00000')
        ->assertSee('23,17 €');
})->group('payments');

it('counts an excess once, whether a line or the affiliation carries it', function (): void {
    affiliationWithLines(60, [['due' => 60, 'paid' => 67.31, 'status' => 'paid']]);

    overpaidTab()
        ->assertSee('7,31 €')
        ->assertDontSee('14,62 €');
})->group('payments');

it('keeps a line paid over its due out of the tab while the affiliation still owes more', function (): void {
    affiliationWithLines(125, [
        ['due' => 60, 'paid' => 65.19, 'status' => 'paid'],
        ['due' => 65, 'paid' => 0, 'status' => 'pending'],
    ]);

    overpaidTab()->assertDontSee('779/0926/00000');
})->group('payments');
