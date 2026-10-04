<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendPaymentReminderJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function treasuryTournamentPayment(User $user, Tournament $tournament, string $status = 'pending')
{
    $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

    $registration = TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->first();

    static $counter = 0;
    $counter++;

    return $registration->payment()->create([
        'reference' => sprintf('TSY/2026/%05d', $counter),
        'amount_due' => 10,
        'amount_paid' => 0,
        'status' => $status,
    ]);
}

function treasuryMeetingPayment(User $user, Meeting $meeting, string $status = 'pending')
{
    $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

    $registration = $meeting->users()->where('users.id', $user->id)->first()->registration;

    static $counter = 0;
    $counter++;

    return $registration->payment()->create([
        'reference' => sprintf('MTG/2026/%05d', $counter),
        'amount_due' => 10,
        'amount_paid' => 0,
        'status' => $status,
    ]);
}

/** Ouvre un remboursement de $amount euros sur la chose que $claim fait payer. */
function treasuryOpenRefundOn(Payment $claim, float $amount): Payment
{
    return Payment::forceCreate([
        'payable_type' => $claim->payable_type,
        'payable_id' => $claim->payable_id,
        'reference' => 'REF/' . $claim->reference,
        'payment_method' => 'refund',
        'amount_due' => $amount,
        'amount_paid' => 0,
        'status' => 'to_refund',
    ]);
}

function mountTreasury(User $actor)
{
    // The screen answers to the treasury duty; these tests are about what the
    // modals render, so the actor needs it.
    $actor->assignRole(Role::TREASURY->value);

    return Livewire::actingAs($actor)
        ->test('pages::club-admin.treasury.payments');
}

// ── table — event name ────────────────────────────────────────────────────────

describe('treasury table — event name', function (): void {
    it('renders the tournament name in the table row', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $tournament = paymentTournament(['name' => 'Winter Classic']);
        treasuryTournamentPayment($member, $tournament);

        mountTreasury($admin)
            ->assertSee('Winter Classic');
    });

    it('renders the tournament name and member name together in the same row', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Durand']);
        $tournament = paymentTournament(['name' => 'Autumn Trophy']);
        treasuryTournamentPayment($member, $tournament);

        mountTreasury($admin)
            ->assertSee('Alice Durand')
            ->assertSee('Autumn Trophy');
    });

    it('renders subscription payments without crashing', function (): void {
        $admin = User::factory()->create();
        $subscription = Subscription::factory()->create();

        $subscription->payments()->create([
            'reference' => 'SUB/2026/00001',
            'amount_due' => 80,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        mountTreasury($admin)
            ->assertSee('SUB/2026/00001');
    });

    it('renders the meeting payer name and meeting label together', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Bruno', 'last_name' => 'Lemaire']);
        $meeting = Meeting::factory()->confirmed()->create([
            'created_by' => $admin->id,
            'title' => 'Comite Juin 2026',
        ]);
        treasuryMeetingPayment($member, $meeting);

        mountTreasury($admin)
            ->assertSee('Bruno Lemaire')
            ->assertSee(__('Meeting'))
            ->assertSee('Comite Juin 2026');
    });

    it('renders the subscription payer name and cotisation label together', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Carla', 'last_name' => 'Petit']);
        $subscription = Subscription::factory()->for($member)->create();

        $subscription->payments()->create([
            'reference' => 'SUB/2026/00099',
            'amount_due' => 80,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        mountTreasury($admin)
            ->assertSee('Carla Petit')
            ->assertSee(__('Subscription'))
            ->assertSee($subscription->season->name);
    });
});

// ── bank import removed from payments page (I1) ───────────────────────────────

describe('treasury payments — bank import removed (I1)', function (): void {
    it('no longer exposes the silent bank import method on the payments page', function (): void {
        $admin = User::factory()->create();

        expect(fn () => mountTreasury($admin)->call('processImport'))
            ->toThrow(MethodNotFoundException::class);
    });

    it('routes committee members to the Transactions import instead', function (): void {
        $admin = User::factory()->create();

        mountTreasury($admin)
            ->assertDontSee(__('Start Import'))
            ->assertSeeHtml(route('admin.treasury.transactions'));
    });
});

// ── reconcile modal — meeting label ───────────────────────────────────────────

describe('reconcile modal — meeting label', function (): void {
    it('renders the meeting label in the reconcile modal header', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $meeting = Meeting::factory()->confirmed()->create([
            'created_by' => $admin->id,
            'title' => 'Reunion strategique',
        ]);
        $payment = treasuryMeetingPayment($member, $meeting);

        mountTreasury($admin)
            ->call('openReconcile', $payment->id)
            ->assertSet('reconcileModal', true)
            ->assertSee('Reunion strategique');
    });
});

// ── reconcile modal — tournament name ─────────────────────────────────────────

describe('reconcile modal — tournament name', function (): void {
    it('renders the tournament name in the reconcile modal header', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $tournament = paymentTournament(['name' => 'Grand Prix Final']);
        $payment = treasuryTournamentPayment($member, $tournament);

        mountTreasury($admin)
            ->call('openReconcile', $payment->id)
            ->assertSet('reconcileModal', true)
            ->assertSee('Grand Prix Final');
    });
});

// ── reconcile modal — match verdict ───────────────────────────────────────────

describe('reconcile modal — match verdict', function (): void {
    it('names the guardian whose IBAN paid, instead of a bare amount badge', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Quentin', 'last_name' => 'Vandevelde', 'iban' => null]);
        $guardian = Guardian::factory()->create([
            'first_name' => 'Michel',
            'last_name' => 'Michotte',
            'iban' => 'BE68 5390 0754 7034',
        ]);
        $member->guardians()->attach($guardian->id);

        $subscription = Subscription::factory()->create(['user_id' => $member->id]);
        $payment = $subscription->payments()->create([
            'reference' => 'RCN/2026/00001',
            'amount_due' => 150,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        Transaction::create([
            'date' => now(),
            'amount' => 150,
            'counterparty_name' => 'M ET MME MICHEL MICHOTTE',
            'counterparty_bank_account' => 'BE68539007547034',
            'free_reference' => 'vandevelde Quentin affiliation 2025-2026',
            'description' => 'VIREMENT EUROPEEN',
        ]);

        mountTreasury($admin)
            ->call('openReconcile', $payment->id)
            ->assertSee(__('Strong match'))
            ->assertSee(__(':name (guardian) IBAN', ['name' => 'Michel Michotte']));
    });
});

// ── search stays inside the active tab ────────────────────────────────────────

describe('cancelling an open refund', function (): void {
    it('cancels the refund line, and the affiliation overpayment shows again', function (): void {
        $subscription = Subscription::factory()->create(['amount_due' => 100]);
        $claim = $subscription->payments()->create([
            'reference' => 'AFF/2026/00001',
            'amount_due' => 120,
            'amount_paid' => 120,
            'status' => 'paid',
        ]);
        $refund = treasuryOpenRefundOn($claim, 20);

        expect($claim->overpayment())->toBe(0.0);

        mountTreasury(User::factory()->create())
            ->set('selected', [(string) $refund->id])
            ->call('bulkCancelRefund');

        expect($refund->refresh()->status)->toBe('cancelled')
            ->and($claim->refresh()->overpayment())->toBe(20.0)
            ->and($subscription->refresh()->netAmountPaid())->toBe(120.0);
    });

    it('cancels the refund line, and the tournament overpayment shows again', function (): void {
        $claim = treasuryTournamentPayment(User::factory()->create(), Tournament::factory()->create());
        $claim->update(['amount_paid' => 15, 'status' => 'paid']);
        $refund = treasuryOpenRefundOn($claim, 5);

        expect($claim->overpayment())->toBe(0.0);

        mountTreasury(User::factory()->create())
            ->set('selected', [(string) $refund->id])
            ->call('bulkCancelRefund');

        expect($refund->refresh()->status)->toBe('cancelled')
            ->and($claim->refresh()->overpayment())->toBe(5.0);
    });

    it('lists the affiliation in the overpaid tab once its refund is cancelled', function (): void {
        $subscription = Subscription::factory()->create(['amount_due' => 100]);
        $claim = $subscription->payments()->create([
            'reference' => 'AFF/2026/00002',
            'amount_due' => 120,
            'amount_paid' => 120,
            'status' => 'paid',
        ]);
        $refund = treasuryOpenRefundOn($claim, 20);

        $page = mountTreasury(User::factory()->create())
            ->set('statusFilter', 'overpaid')
            ->assertDontSee('AFF/2026/00002');

        $page->set('selected', [(string) $refund->id])
            ->call('bulkCancelRefund')
            ->assertSee('AFF/2026/00002');
    });
});

describe('treasury table — cost of a render', function (): void {
    beforeEach(function (): void {
        foreach (range(1, 3) as $index) {
            Subscription::factory()->create()->payments()->create([
                'reference' => sprintf('COST/2026/%05d', $index),
                'amount_due' => 125,
                'amount_paid' => 0,
                'status' => 'pending',
            ]);
        }
    });

    it('costs as many queries for nine rows as for three, overpaid tab included', function (string $tab): void {
        $addRowsTo = function (int $from, int $to) use ($tab): void {
            foreach (range($from, $to) as $index) {
                Subscription::factory()->create(['amount_due' => 100])->payments()->create([
                    'reference' => sprintf('COST/2026/%05d', $index),
                    'amount_due' => $tab === 'overpaid' ? 100 : 125,
                    'amount_paid' => $tab === 'overpaid' ? 130 : 0,
                    'status' => $tab === 'overpaid' ? 'paid' : 'pending',
                ]);
            }
        };

        $treasurer = User::factory()->create();
        mountTreasury($treasurer);

        $queriesFor = function () use ($tab, $treasurer): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            Livewire::actingAs($treasurer)
                ->test('pages::club-admin.treasury.payments')
                ->set('statusFilter', $tab);

            return count(DB::getQueryLog());
        };

        $addRowsTo(4, 6);
        $withThreeRows = $queriesFor();
        $addRowsTo(7, 12);

        // Le trop-perçu arrive calculé par la base, avec les lignes : il coûtait
        // jusqu'à quatre requêtes par ligne d'affiliation.
        expect($queriesFor())->toBe($withThreeRows);
    })->with(['pending', 'overpaid']);

    it('counts in SQL the rows the table lists', function (): void {
        $page = mountTreasury(User::factory()->create());

        expect($page->instance()->getTotalMatchingCount())->toBe(3)
            ->and($page->instance()->payments()->total())->toBe(3);
    });
});

describe('treasury search — stays inside the active tab', function (): void {
    it('does not leak a paid payment into the to-refund tab when searching by name', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Lemoine']);
        $subscription = Subscription::factory()->create(['user_id' => $member->id]);

        $subscription->payments()->create([
            'reference' => 'LEAK/2026/00001',
            'amount_due' => 215,
            'amount_paid' => 215,
            'status' => 'paid',
        ]);

        mountTreasury($admin)
            ->set('statusFilter', 'to_refund')
            ->set('search', 'Lemoine')
            ->assertDontSee('LEAK/2026/00001');
    });

    it('still finds the payment in its own tab, by member name and by reference', function (): void {
        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Lemoine']);
        $subscription = Subscription::factory()->create(['user_id' => $member->id]);

        $subscription->payments()->create([
            'reference' => 'LEAK/2026/00001',
            'amount_due' => 215,
            'amount_paid' => 215,
            'status' => 'paid',
        ]);

        // Les deux branches du OR, séparément : borner la recherche au statut ne
        // doit pas revenir à amputer l'une d'elles.
        mountTreasury($admin)
            ->set('statusFilter', 'paid')
            ->set('search', 'Lemoine')
            ->assertSee('LEAK/2026/00001')
            ->set('search', 'LEAK/2026/00001')
            ->assertSee('Nadia Lemoine');
    });

    it('does not reach settled payments when selecting every search result', function (): void {
        Queue::fake();

        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Lemoine']);
        $subscription = Subscription::factory()->create(['user_id' => $member->id]);

        $subscription->payments()->create([
            'reference' => 'LEAK/2026/00001',
            'amount_due' => 215,
            'amount_paid' => 215,
            'status' => 'paid',
        ]);
        $subscription->payments()->create([
            'reference' => 'LEAK/2026/00002',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        // « Tout sélectionner » lit la requête filtrée de la liste : ce que
        // l'action de masse enfile dit ce que la sélection a retenu.
        mountTreasury($admin)
            ->set('statusFilter', 'pending')
            ->set('search', 'Lemoine')
            ->set('selectAll', true)
            ->call('selectAllResults')
            ->call('bulkSendReminder');

        Queue::assertPushed(SendPaymentReminderJob::class, 1);
    });
});
