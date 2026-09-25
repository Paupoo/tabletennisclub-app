<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * L'écran Transactions était binaire — rapprochée ou non — parce que le lien
 * l'était : une colonne `unique()` ne sait dire que « attachée » ou « pas
 * attachée ». Une ligne peut désormais être affectée en partie, et le trésorier
 * doit pouvoir distinguer les trois cas.
 */
const SETTLEMENT_COMPONENT = 'pages::club-admin.treasury.transactions';

function settlementScreen(): Testable
{
    return Livewire::actingAs(User::factory()->isAdmin()->create())->test(SETTLEMENT_COMPONENT);
}

function allocatableTransaction(float $amount, float $allocate = 0.0): Transaction
{
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 1000,
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => $amount,
        'counterparty_name' => $member->full_name,
    ]);

    if ($allocate > 0.0) {
        $payment = $subscription->payments()->create([
            'reference' => sprintf('700/0000/%05d', $transaction->id),
            'amount_due' => 1000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        (new AllocateTransactionAction)($transaction, [$payment->id => $allocate]);
    }

    return $transaction->fresh();
}

/** @return Collection<int, int> */
function idsUnder(string $filter): Collection
{
    return collect(settlementScreen()->set('reconciledFilter', $filter)->viewData('transactions')->items())
        ->pluck('id');
}

it('sorts transactions into untouched, partly allocated and settled', function (): void {
    $untouched = allocatableTransaction(300.0);
    $partly = allocatableTransaction(300.0, 120.0);
    $settled = allocatableTransaction(300.0, 300.0);

    expect(idsUnder('unreconciled'))->toContain($untouched->id)
        ->and(idsUnder('unreconciled'))->not->toContain($partly->id)
        ->and(idsUnder('unreconciled'))->not->toContain($settled->id);

    expect(idsUnder('partial'))->toContain($partly->id)
        ->and(idsUnder('partial'))->not->toContain($untouched->id)
        ->and(idsUnder('partial'))->not->toContain($settled->id);

    expect(idsUnder('reconciled'))->toContain($settled->id)
        ->and(idsUnder('reconciled'))->not->toContain($partly->id);
})->group('payments', 'transactions');

/**
 * Un reliquat abandonné clôt la ligne aussi sûrement qu'une affectation
 * complète. Sans ça, l'arrondi d'un membre la laisserait « partiellement
 * affectée » à vie et le filtre perdrait sa valeur en une saison.
 */
it('counts a written-off residue as settled', function (): void {
    $transaction = allocatableTransaction(305.0, 300.0);

    expect(idsUnder('partial'))->toContain($transaction->id);

    (new SettleTransactionResidueAction)($transaction->fresh(), 'Arrondi du membre');

    expect(idsUnder('reconciled'))->toContain($transaction->id)
        ->and(idsUnder('partial'))->not->toContain($transaction->id);
})->group('payments', 'transactions');

/**
 * Les tuiles et le filtre doivent désigner le même ensemble. Deux requêtes
 * séparées l'ont déjà démenti une fois sur cet écran.
 */
it('keeps every tile agreeing with the filter that shows the same rows', function (): void {
    allocatableTransaction(300.0);
    allocatableTransaction(300.0, 120.0);
    allocatableTransaction(300.0, 300.0);

    $stats = settlementScreen()->instance()->stats;

    foreach (['unreconciled', 'partial', 'reconciled'] as $key) {
        expect(settlementScreen()->set('reconciledFilter', $key)->viewData('transactions')->total())
            ->toBe($stats[$key], "la tuile « {$key} » ne dit pas ce que son filtre montre");
    }
})->group('payments', 'transactions');

/**
 * Un virement sortant jamais rapproché est un remboursement parti sans avoir
 * été rattaché à personne — exactement le travail que le trésorier doit voir.
 *
 * L'ancienne règle l'excluait au motif qu'« un débit n'a pas de paiement par
 * nature ». C'était vrai du modèle d'avant, où les remboursements vivaient dans
 * une seconde colonne que `has('payment')` ne regardait pas.
 */
it('shows an unallocated outgoing transfer as work still to do', function (): void {
    $debit = allocatableTransaction(-90.0);

    expect(idsUnder('unreconciled'))->toContain($debit->id);
})->group('payments', 'transactions');

/**
 * Le cas B, depuis la transaction : une mère vire 730 € pour ses deux enfants.
 *
 * Le sens paiement → transaction sait déjà le faire depuis que le lien n'est
 * plus unique, mais il oblige le trésorier à ouvrir deux fiches et à retrouver
 * deux fois la même ligne de relevé. Le geste naturel part du virement.
 */
it('splits one transfer across several payments from the transaction drawer', function (): void {
    $mother = User::factory()->create();

    $children = collect([1, 2])->map(function (): object {
        $child = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $child->id,
            'status' => 'confirmed',
            'amount_due' => 365,
        ]);

        return (object) [
            'subscription' => $subscription,
            'payment' => $subscription->payments()->create([
                'reference' => sprintf('300/0000/%05d', $child->id),
                'amount_due' => 365,
                'amount_paid' => 0,
                'status' => 'pending',
            ]),
        ];
    });

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => 730.0,
        'counterparty_name' => $mother->full_name,
    ]);

    settlementScreen()
        ->call('openAllocation', $transaction->id)
        ->set('allocations', [
            (string) $children[0]->payment->id => 365.0,
            (string) $children[1]->payment->id => 365.0,
        ])
        ->call('confirmAllocation')
        ->assertHasNoErrors();

    expect($children[0]->subscription->fresh()->balanceDue())->toBe(0.0)
        ->and($children[1]->subscription->fresh()->balanceDue())->toBe(0.0)
        ->and($transaction->fresh()->isSettled())->toBeTrue();
})->group('payments', 'transactions');

/**
 * I1 tient aussi de ce côté : le tiroir refuse d'inventer de l'argent, et il
 * le dit au trésorier plutôt que d'échouer en silence.
 */
it('refuses an allocation that exceeds the transfer, and writes nothing', function (): void {
    $transaction = allocatableTransaction(200.0);

    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 365,
    ]);

    $payment = $subscription->payments()->create([
        'reference' => '400/0000/00001',
        'amount_due' => 365,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    settlementScreen()
        ->call('openAllocation', $transaction->id)
        ->set('allocations', [(string) $payment->id => 250.0])
        ->call('confirmAllocation');

    expect($payment->fresh()->amount_paid)->toBe(0.0)
        ->and($transaction->fresh()->allocated_amount)->toBe(0.0);
})->group('payments', 'transactions');

/**
 * Solder un reliquat depuis le tiroir : le troisième geste du cas C.
 */
it('writes off a residue from the drawer, with a reason', function (): void {
    $transaction = allocatableTransaction(305.0, 300.0);

    settlementScreen()
        ->call('openAllocation', $transaction->id)
        ->set('residueReason', 'Arrondi du membre, acquis au club')
        ->call('settleResidue')
        ->assertHasNoErrors();

    expect($transaction->fresh()->isSettled())->toBeTrue()
        ->and($transaction->fresh()->settled_reason)->toBe('Arrondi du membre, acquis au club');
})->group('payments', 'transactions');

/**
 * Le tiroir ne doit pas aller chercher les membres un par un.
 *
 * `TransactionMatcher::payer()` lit `$payable->user` pour une affiliation, une
 * inscription au tournoi **et** une participation à une réunion. N'en charger
 * qu'un seul fait partir une requête par ligne — et sur la base de
 * démonstration, dix-neuf des créances ouvertes sont des inscriptions.
 *
 * On compte les requêtes plutôt que d'attendre une exception :
 * `Model::preventLazyLoading()` est actif mais ne lève rien dans cette suite,
 * et une assertion sur `relationLoaded()` est aveugle — elle observe la
 * relation **après** que le code l'a chargée, donc elle ne peut jamais échouer.
 */
it('loads every payable member in one go, whatever the payable', function (): void {
    $member = User::factory()->create();

    $tournament = Tournament::factory()->create(['price' => 15]);
    $tournament->users()->attach($member->id, ['registration_status' => 'registered']);

    $registration = TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $member->id)
        ->firstOrFail();

    $registration->payment()->create([
        'reference' => '800/0000/00001',
        'amount_due' => 15,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => 15.0,
        'counterparty_name' => $member->full_name,
    ]);

    $component = settlementScreen()->call('openAllocation', $transaction->id);

    $singleUserLookups = 0;

    DB::listen(function (QueryExecuted $query) use (&$singleUserLookups): void {
        // La signature d'un chargement paresseux : on va chercher UN membre par
        // son identifiant. Le chargement anticipé, lui, les prend en lot
        // (`where id in (...)`).
        if (preg_match('/from "users" where "users"\."id" = \?/', $query->sql) === 1) {
            $singleUserLookups++;
        }
    });

    $candidates = $component->instance()->allocationCandidates();

    expect($candidates->pluck('reference'))->toContain('800/0000/00001')
        ->and($singleUserLookups)->toBe(0, 'le barème est allé chercher des membres un par un');
})->group('payments', 'transactions');

/**
 * Un virement sortant doit trouver les remboursements qui l'attendent.
 *
 * Deux formes de `to_refund` coexistent : celle de
 * RequestSubscriptionRefundAction, dont `amount_paid` vaut zéro jusqu'au
 * virement, et celle héritée d'un paiement encaissé dont on a basculé le
 * statut, qui garde `amount_paid = amount_due`. Un reste calculé sur
 * `amount_due - amount_paid` écarte la seconde : le tiroir annonçait « aucun
 * paiement n'attend d'argent dans ce sens » devant six remboursements ouverts.
 *
 * Ce qui compte est ce qui est déjà **sorti**, donc les crédits adossés à une
 * transaction de débit.
 */
it('offers a refund whose line still carries the money that came in', function (): void {
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 120,
    ]);

    // La forme héritée : encaissée, puis basculée en `to_refund`. Écrite en SQL
    // brut parce que le modèle la refuse maintenant — c'est bien l'ancien code
    // qui l'a produite, et une base dont la migration de normalisation n'a pas
    // encore tourné en porte encore.
    DB::table('payments')->insert([
        'reference' => '900/0000/00001',
        'payable_type' => $subscription->getMorphClass(),
        'payable_id' => $subscription->id,
        'amount_due' => 12000,
        'amount_paid' => 12000,
        'status' => 'to_refund',
        'payment_method' => 'Wire',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $outgoing = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN FAVEUR DE TIERS',
        'amount' => -120.0,
        'counterparty_name' => $subscription->user->full_name,
    ]);

    $candidates = settlementScreen()
        ->call('openAllocation', $outgoing->id)
        ->instance()
        ->allocationCandidates();

    expect($candidates->pluck('reference'))->toContain('900/0000/00001');
})->group('payments', 'transactions');

/**
 * Le tiroir doit dire qui a payé, et pourquoi chaque candidat est proposé.
 *
 * Rempli, il affichait trente-quatre lignes « doit 10,00 € » sans le nom du
 * tiers, sans le moindre signal de pertinence et avec des cases vides à
 * remplir une par une. Le barème calculait déjà le verdict — il était jeté.
 */
it('names the payer and says why each candidate is proposed', function (): void {
    $member = User::factory()->create(['first_name' => 'Jade', 'last_name' => 'Delfosse']);

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 95,
    ]);

    $payment = $subscription->payments()->create([
        'reference' => '023/0926/03979',
        'amount_due' => 95,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 95.0,
        'counterparty_name' => 'DELFOSSE Jade',
        'structured_reference' => $payment->reference,
    ]);

    $screen = settlementScreen()->call('openAllocation', $transaction->id);

    // Le tiers, pour savoir de qui vient l'argent.
    $screen->assertSee('DELFOSSE Jade');

    // Et la raison du rapprochement, attachée à la ligne.
    $candidate = $screen->instance()->allocationCandidates()->firstWhere('reference', '023/0926/03979');

    expect($candidate->match)->not->toBeNull()
        ->and($candidate->match->reasons)->not->toBeEmpty();
})->group('payments', 'transactions');

/**
 * Un clic remplit le montant, plutôt qu'un calcul de tête.
 */
it('fills in the amount it suggests for a candidate', function (): void {
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 125,
    ]);

    $payment = $subscription->payments()->create([
        'reference' => '024/0926/00001',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => 60.0,
        'counterparty_name' => 'Payeur',
    ]);

    $screen = settlementScreen()
        ->call('openAllocation', $transaction->id)
        ->call('suggestAllocation', $payment->id);

    // Le plus petit des deux restes : la transaction n'a que 60 € à placer.
    expect($screen->get('allocations'))->toBe([(string) $payment->id => 60.0]);
})->group('payments', 'transactions');

/**
 * Quand rien ne correspond, l'écran doit le dire.
 *
 * Un virement d'un tiers inconnu, sans communication reconnaissable, affichait
 * vingt-et-un paiements en attente comme s'il s'agissait de suggestions. Aucun
 * n'en était une : le barème ne leur trouve aucune raison. Une liste sans
 * verdict se lit comme une liste de propositions, et c'est un contresens.
 */
it('says plainly when the scale recognises nobody', function (): void {
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 10,
    ]);

    $subscription->payments()->create([
        'reference' => '023/0926/03979',
        'amount_due' => 10,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    // Le cas 13 du relevé de démonstration : un tiers qu'on ne connaît pas,
    // une communication qui ne désigne rien.
    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 95.0,
        'counterparty_name' => 'DUBOIS Jean-Pierre',
        'structured_reference' => '999/9999/99999',
    ]);

    $screen = settlementScreen()->call('openAllocation', $transaction->id);

    $candidates = $screen->instance()->allocationCandidates();

    // Aucun candidat n'a de raison : c'est le fait que l'écran doit annoncer.
    expect($candidates->filter(fn ($c): bool => $c->match?->reasons !== []))->toBeEmpty();

    $screen->assertSee(__('No payment matches this transfer'));
})->group('payments', 'transactions');

/**
 * Le lien du bandeau doit ouvrir le geste, pas la liste.
 *
 * Le trésorier vient de rapprocher depuis l'écran Paiements et sait ce qu'il
 * veut faire. Le déposer devant cinquante-quatre lignes à retrouver la sienne,
 * c'est lui rendre le problème qu'on venait de lui signaler.
 */
it('opens the drawer straight away when the url points at a transaction', function (): void {
    $transaction = allocatableTransaction(300.0);

    Livewire::actingAs(User::factory()->isAdmin()->create())
        ->withQueryParams(['allocate' => $transaction->id])
        ->test(SETTLEMENT_COMPONENT)
        ->assertSet('allocationModal', true)
        ->assertSet('allocationTransactionId', $transaction->id);
})->group('payments', 'transactions');
