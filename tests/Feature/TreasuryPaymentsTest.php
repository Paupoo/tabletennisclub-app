<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentRegistration;
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

function mountTreasury(User $admin)
{
    return Livewire::actingAs($admin)
        ->test('pages::club-admin.users.payments');
}

// ── table — event name ────────────────────────────────────────────────────────

describe('treasury table — event name', function () {
    it('renders the tournament name in the table row', function () {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $tournament = paymentTournament(['name' => 'Winter Classic']);
        treasuryTournamentPayment($member, $tournament);

        mountTreasury($admin)
            ->assertSee('Winter Classic');
    });

    it('renders the tournament name and member name together in the same row', function () {
        $admin = User::factory()->create();
        $member = User::factory()->create(['first_name' => 'Alice', 'last_name' => 'Durand']);
        $tournament = paymentTournament(['name' => 'Autumn Trophy']);
        treasuryTournamentPayment($member, $tournament);

        mountTreasury($admin)
            ->assertSee('Alice Durand')
            ->assertSee('Autumn Trophy');
    });

    it('renders subscription payments without crashing', function () {
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
});

// ── reconcile modal — tournament name ─────────────────────────────────────────

describe('reconcile modal — tournament name', function () {
    it('renders the tournament name in the reconcile modal header', function () {
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
