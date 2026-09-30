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
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\ValueObjects\FiscalYear;
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

/**
 * Two accounts and two tills over the first quarter of 2026: the current
 * account moves every month, the savings account was only imported in
 * January, and the tills add up to one series.
 */
function fpTreasuryHistory(): void
{
    Carbon::setTestNow('2026-03-20 12:00:00');
    $current = BankAccount::factory()->create(['name' => 'Compte courant', 'iban' => 'BE68539007547034']);
    $savings = BankAccount::factory()->savings()->create(['name' => 'Compte épargne', 'iban' => 'BE71096123456769']);
    foreach ([['2025-12-15', 7000.0], ['2026-01-10', 8000.0], ['2026-02-27', 8500.0], ['2026-03-05', 8200.0]] as [$date, $balance]) {
        Transaction::create(['date' => $date, 'description' => 'L', 'amount' => 1.0, 'bank_account_id' => $current->id, 'balance_after' => $balance]);
    }
    Transaction::create(['date' => '2026-01-02', 'description' => 'Intérêts', 'amount' => 10.0, 'bank_account_id' => $savings->id, 'balance_after' => 15010.0]);

    $recorder = User::factory()->create();
    foreach ([['Caisse du club', '2025-11-02 10:00:00', 15000], ['Caisse du club', '2026-02-14 10:00:00', 2500], ['Caisse des tournois', '2026-03-10 10:00:00', 4000]] as [$name, $at, $cents]) {
        $till = CashRegister::firstOrCreate(['name' => $name]);
        $entry = CashRegisterEntry::create(['cash_register_id' => $till->id, 'amount' => $cents, 'reason' => 'x', 'recorded_by_id' => $recorder->id]);
        $entry->forceFill(['created_at' => $at])->saveQuietly();
    }
}

it('reads the money held at each month end of the year, account by account and the tills together', function (): void {
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 1]);
    Club::forgetOwnClub();
    fpTreasuryHistory();

    $history = (new FinancialPosition)->treasuryByMonth(FiscalYear::startingIn(2026), Carbon::parse('2026-03-20'));

    expect(array_column($history['series'], 'label'))->toBe(['Compte courant', 'Compte épargne', 'Caisses'])
        ->and(array_map(fn (array $month): array => [
            $month['day']->toDateString(),
            array_map(fn (array $value): ?float => $value['balance'], $month['values']),
            $month['total'],
        ], $history['months']))
        ->toBe([
            ['2026-01-31', [8000.0, 15010.0, 150.0], 23160.0],
            ['2026-02-28', [8500.0, 15010.0, 175.0], 23685.0],
            // A month still running stops at the day read.
            ['2026-03-20', [8200.0, 15010.0, 215.0], 23425.0],
        ])
        // The savings balance of March dates from its January import.
        ->and($history['months'][2]['values'][1]['as_of']?->toDateString())->toBe('2026-01-02');
});

it('says how much the treasury moved since the year began, and which balance is too old to trust', function (): void {
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 1]);
    Club::forgetOwnClub();
    fpTreasuryHistory();

    $summary = (new FinancialPosition)->treasurySummary(FiscalYear::startingIn(2026), Carbon::parse('2026-03-20'));

    // 31 December 2025: 7 000 € + nothing known on savings + 150 € of till.
    // 20 March 2026: 23 425 €.
    expect($summary['total'])->toBe(23425.0)
        ->and($summary['change'])->toBe(16275.0)
        ->and(array_map(fn (array $holder): array => [$holder['name'], $holder['as_of']?->toDateString()], $summary['stale']))
        ->toBe([['Compte épargne', '2026-01-02']]);
});
