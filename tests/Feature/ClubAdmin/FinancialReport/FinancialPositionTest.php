<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Payments\OpenRefundAction;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Services\FinancialPosition;
use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Where the club stands on a day: what it is owed, what it owes, and the
 * money it holds. States, not flows — nothing here is compared with a year
 * before.
 */
beforeEach(function (): void {
    Storage::fake('local');
});

function fpClaim(Subscription $subscription, float $due, float $paid = 0, string $status = 'pending'): Payment
{
    $payment = new Payment(['reference' => (new GeneratePaymentReference)(), 'amount_due' => $due, 'amount_paid' => $paid, 'status' => $status, 'payment_method' => 'electronic']);
    $payment->payable()->associate($subscription);
    $payment->save();

    return $payment;
}

it('adds up what members still owe and the income documents not received yet', function (): void {
    $subscription = Subscription::factory()->create();
    fpClaim($subscription, 120.0, 50.0);
    fpClaim($subscription, 30.0);
    fpClaim($subscription, 80.0, 80.0, 'paid');
    fpClaim($subscription, 40.0, 0, 'cancelled');
    SupportingDocument::factory()->income(IncomeCategory::Subsidies)->create(['amount' => 950.0]);
    SupportingDocument::factory()->expense(ExpenseCategory::Event)->create(['amount' => 95.0]);

    // 70 € + 30 € of payments, 950 € of a subsidy promised.
    expect((new FinancialPosition)->openReceivables())->toBe(['amount' => 1050.0, 'count' => 3]);
});

it('adds up the refunds and expense reports to pay and the invoices not paid yet', function (): void {
    (new OpenRefundAction)->forPayable(Subscription::factory()->create(), 60.0, 'BE68539007547034');
    (new OpenRefundAction)->forPayable(ExpenseReport::factory()->accepted(45.5)->create(['amount' => 45.5]), 45.5);
    SupportingDocument::factory()->expense(ExpenseCategory::Event)->create(['amount' => 95.0]);
    SupportingDocument::factory()->expense(ExpenseCategory::SportsEquipment)->create(['amount' => 210.0]);
    $paid = SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['amount' => 300.0]);
    (new LinkSupportingDocument)($paid, Transaction::create(['date' => '2026-09-01', 'description' => 'BLOCRY', 'amount' => -300.0]));

    expect((new FinancialPosition)->openDebts())->toBe(['amount' => 410.5, 'count' => 4]);
});

it('counts the active members who owe the club something', function (): void {
    $season = makeActiveSeason();
    $owing = User::factory()->create();
    $owingToo = User::factory()->create();
    $clear = User::factory()->create();
    $applicant = User::factory()->create();
    fpClaim(Subscription::factory()->create(['user_id' => $owing->id, 'season_id' => $season->id, 'status' => 'confirmed']), 120.0);
    fpClaim(Subscription::factory()->create(['user_id' => $owingToo->id, 'season_id' => $season->id, 'status' => 'paid']), 30.0);
    fpClaim(Subscription::factory()->create(['user_id' => $clear->id, 'season_id' => $season->id, 'status' => 'confirmed']), 120.0, 120.0, 'paid');
    // Not a member yet: the committee has not confirmed the affiliation.
    fpClaim(Subscription::factory()->create(['user_id' => $applicant->id, 'season_id' => $season->id, 'status' => 'pending']), 120.0);

    expect((new FinancialPosition)->membersWithOpenDebt())->toBe(['count' => 2, 'active' => 3]);
});

it('reads the money held on a day from each account and each till, with the day each balance dates from', function (): void {
    Carbon::setTestNow('2026-09-30 12:00:00');
    $current = BankAccount::factory()->create(['name' => 'Compte courant', 'iban' => 'BE68539007547034']);
    $savings = BankAccount::factory()->savings()->create(['name' => 'Compte épargne', 'iban' => 'BE71096123456769']);
    Transaction::create(['date' => '2026-06-01', 'description' => 'A', 'amount' => 100.0, 'bank_account_id' => $current->id, 'balance_after' => 8100.0]);
    Transaction::create(['date' => '2026-06-20', 'description' => 'B', 'amount' => -50.0, 'bank_account_id' => $current->id, 'balance_after' => 8050.0]);
    Transaction::create(['date' => '2026-07-01', 'description' => 'C', 'amount' => 2000.0, 'bank_account_id' => $savings->id, 'balance_after' => 17000.0]);
    $till = CashRegister::create(['name' => 'Caisse du club']);
    $recorder = User::factory()->create();
    foreach ([['2026-05-01 10:00:00', 15000], ['2026-06-25 10:00:00', -2500], ['2026-07-02 10:00:00', 1000]] as [$at, $cents]) {
        $entry = CashRegisterEntry::create(['cash_register_id' => $till->id, 'amount' => $cents, 'reason' => 'x', 'recorded_by_id' => $recorder->id]);
        $entry->forceFill(['created_at' => $at])->saveQuietly();
    }

    $treasury = (new FinancialPosition)->treasuryAt(Carbon::parse('2026-06-30'));

    expect($treasury['total'])->toBe(8175.0)
        ->and(array_map(fn (array $holder): array => [$holder['name'], $holder['kind'], $holder['balance'], $holder['as_of']?->toDateString()], $treasury['holders']))
        ->toBe([
            ['Compte courant', 'current', 8050.0, '2026-06-20'],
            ['Compte épargne', 'savings', null, null],
            ['Caisse du club', 'cash', 125.0, '2026-06-25'],
        ]);
});
