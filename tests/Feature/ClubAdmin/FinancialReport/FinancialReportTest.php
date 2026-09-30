<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Actions\ClubAdmin\Payments\OpenRefundAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Illuminate\Support\Facades\Storage;

/**
 * The financial report counts money at the date it moved — a bank line on
 * its date, a cash movement on the day it was recorded — and files it under
 * the poste its documents or its website payments say. Every amount below is
 * worked out by hand.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 1]);
    Club::forgetOwnClub();
});

function frLine(float $amount, string $date, bool $internal = false): Transaction
{
    return Transaction::create([
        'date' => $date,
        'description' => 'VIREMENT',
        'amount' => $amount,
        'is_internal' => $internal,
    ]);
}

function frCash(int $cents, string $recordedAt): CashRegisterEntry
{
    $entry = CashRegisterEntry::create([
        'cash_register_id' => (CashRegister::first() ?? CashRegister::create(['name' => 'Caisse']))->id,
        'amount' => $cents,
        'reason' => 'Mouvement',
        'recorded_by_id' => (User::first() ?? User::factory()->create())->id,
    ]);
    $entry->forceFill(['created_at' => $recordedAt])->saveQuietly();

    return $entry;
}

function frJustify(Transaction|CashRegisterEntry $movement, ExpenseCategory|IncomeCategory $category, float $amount): void
{
    $state = $category instanceof ExpenseCategory
        ? ['expense_category' => $category, 'income_category' => null]
        : ['expense_category' => null, 'income_category' => $category];

    (new LinkSupportingDocument)(SupportingDocument::factory()->create([...$state, 'amount' => $amount]), $movement);
}

it('adds up the justified money of the year, leaves internal movements out and shows what nobody explained', function (): void {
    frJustify(frLine(-300.0, '2026-02-10'), ExpenseCategory::Hall, 300.0);
    frJustify(frLine(500.0, '2026-03-05'), IncomeCategory::Subsidies, 500.0);
    frJustify(frCash(-1250, '2026-04-01 18:00:00'), ExpenseCategory::Bar, 12.5);
    frLine(-2000.0, '2026-05-01', internal: true);
    frLine(40.0, '2026-06-01');
    // Outside the year, on both sides of it.
    frJustify(frLine(-300.0, '2025-12-31'), ExpenseCategory::Hall, 300.0);
    frCash(2000, '2027-01-01 09:00:00');

    $report = FinancialReport::for(FiscalYear::startingIn(2026));

    expect($report->income())->toBe(540.0)
        ->and($report->expenses())->toBe(312.5)
        ->and($report->result())->toBe(227.5)
        ->and($report->incomeByCategory())->toBe(['subsidies' => 500.0, FinancialReport::UNCATEGORISED => 40.0])
        ->and($report->expensesByCategory())->toBe(['hall' => 300.0, 'bar' => 12.5]);
});

/**
 * An affiliation of 280 €: an 80 € licence, 220 € of trainings, less a 20 €
 * family credit. Its money splits 80 / 300 to the licence — 74.67 € of the
 * 280 € — and the rest, 205.33 €, to the trainings.
 */
function frAffiliation(bool $competitive = true): Subscription
{
    return Subscription::factory()->create([
        'subscription_price' => 80,
        'family_credit' => 20,
        'amount_due' => 280,
        'is_competitive' => $competitive,
    ]);
}

function frClaim(Subscription|ExpenseReport $payable, float $amount): Payment
{
    $payment = new Payment(['reference' => (new GeneratePaymentReference)(), 'amount_due' => $amount, 'amount_paid' => 0, 'status' => 'pending', 'payment_method' => 'electronic']);
    $payment->payable()->associate($payable);
    $payment->save();

    return $payment;
}

function frAllocate(Transaction $line, Payment $payment, float $amount): void
{
    (new AllocateTransactionAction)($line, [$payment->id => $amount]);
}

it('splits an affiliation between membership fees and trainings, pro rata of its gross composition', function (): void {
    $affiliation = frAffiliation();
    frAllocate(frLine(280.0, '2026-10-02'), frClaim($affiliation, 280.0), 280.0);

    $report = FinancialReport::for(FiscalYear::startingIn(2026));

    expect($report->incomeByCategory())->toBe(['membership_fees' => 74.67, 'trainings' => 205.33])
        ->and($report->membershipFeesByLicence())->toBe(['recreational' => 0.0, 'competitive' => 74.67])
        ->and($report->expenses())->toBe(0.0);
});

it('deducts a refund from the income it once was, instead of calling it an expense', function (): void {
    $affiliation = frAffiliation(competitive: false);
    frAllocate(frLine(280.0, '2026-10-02'), frClaim($affiliation, 280.0), 280.0);
    $refund = (new OpenRefundAction)->forPayable($affiliation, 60.0, 'BE68539007547034');
    frAllocate(frLine(-60.0, '2026-11-15'), $refund, 60.0);

    $report = FinancialReport::for(FiscalYear::startingIn(2026));

    // 60 € back: 60 × 80 / 300 = 16 € of licence, 44 € of trainings.
    expect($report->incomeByCategory())->toBe(['membership_fees' => 58.67, 'trainings' => 161.33])
        ->and($report->membershipFeesByLicence())->toBe(['recreational' => 58.67, 'competitive' => 0.0])
        ->and($report->income())->toBe(220.0)
        ->and($report->expenses())->toBe(0.0);
});

it('files an expense report refund as an expense of the report category', function (): void {
    $report = ExpenseReport::factory()->accepted(45.5)->create(['category' => ExpenseCategory::Travel, 'amount' => 45.5]);
    $refund = (new OpenRefundAction)->forPayable($report, 45.5);
    frAllocate(frLine(-45.5, '2026-03-20'), $refund, 45.5);

    expect(FinancialReport::for(FiscalYear::startingIn(2026))->expensesByCategory())->toBe(['travel' => 45.5]);
});

it('files the cash of a tournament as event income, on the day it was recorded', function (): void {
    $entry = frCash(1000, '2026-05-10 15:00:00');
    $entry->payable()->associate(Tournament::factory()->create());
    $entry->saveQuietly();

    expect(FinancialReport::for(FiscalYear::startingIn(2026))->incomeByCategory())->toBe(['event_income' => 10.0]);
});

it('keeps the unallocated part of a line to process, and files a written-off residue as other income', function (): void {
    $partial = frLine(150.0, '2026-02-01');
    frAllocate($partial, frClaim(Subscription::factory()->create(['subscription_price' => 120, 'amount_due' => 120, 'family_credit' => 0]), 120.0), 120.0);
    $overpaid = frLine(125.0, '2026-02-02');
    frAllocate($overpaid, frClaim(Subscription::factory()->create(['subscription_price' => 120, 'amount_due' => 120, 'family_credit' => 0]), 120.0), 120.0);
    (new SettleTransactionResidueAction)($overpaid, 'Arrondi laissé au club');

    $report = FinancialReport::for(FiscalYear::startingIn(2026));

    expect($report->incomeByCategory())->toBe(['membership_fees' => 240.0, 'other_income' => 5.0, FinancialReport::UNCATEGORISED => 30.0])
        ->and($report->justification()['count'])->toBe(['reconciled' => 0, 'justified' => 0, 'written_off' => 1, 'internal' => 0, 'to_process' => 1]);
});

it('says how much of the year is explained, in count and in amount', function (): void {
    frAllocate(frLine(120.0, '2026-01-10'), frClaim(Subscription::factory()->create(['subscription_price' => 120, 'amount_due' => 120, 'family_credit' => 0]), 120.0), 120.0);
    frJustify(frLine(-300.0, '2026-02-10'), ExpenseCategory::Hall, 300.0);
    frLine(-500.0, '2026-03-01', internal: true);
    frLine(80.0, '2026-04-01');

    $justification = FinancialReport::for(FiscalYear::startingIn(2026))->justification();

    // 3 of 4 movements are closed; 920 € of 1 000 €.
    expect($justification['count'])->toBe(['reconciled' => 1, 'justified' => 1, 'written_off' => 0, 'internal' => 1, 'to_process' => 1])
        ->and($justification['amount'])->toBe(['reconciled' => 120.0, 'justified' => 300.0, 'written_off' => 0.0, 'internal' => 500.0, 'to_process' => 80.0])
        ->and($justification['closed_count_share'])->toBe(75.0)
        ->and($justification['closed_amount_share'])->toBe(92.0);
});

it('follows a financial year that starts in September, month by month', function (): void {
    Club::own()->update(['fiscal_year_start_month' => 9]);
    Club::forgetOwnClub();

    frJustify(frLine(-100.0, '2025-08-31'), ExpenseCategory::Hall, 100.0);
    frJustify(frLine(-250.0, '2025-09-01'), ExpenseCategory::Hall, 250.0);
    frJustify(frLine(400.0, '2025-12-15'), IncomeCategory::Sponsorship, 400.0);
    frJustify(frCash(-4000, '2026-08-31 20:00:00'), ExpenseCategory::Bar, 40.0);
    frJustify(frLine(900.0, '2026-09-01'), IncomeCategory::Subsidies, 900.0);

    $report = FinancialReport::for(FiscalYear::startingIn(2025));
    $monthly = $report->monthly();

    expect($report->year()->label())->toBe('2025-2026')
        ->and($report->result())->toBe(110.0)
        ->and($monthly)->toHaveCount(12)
        ->and($monthly[0]['month']->format('Y-m'))->toBe('2025-09')
        ->and([$monthly[0]['income'], $monthly[0]['expenses'], $monthly[0]['cumulative']])->toBe([0.0, 250.0, -250.0])
        ->and([$monthly[3]['income'], $monthly[3]['cumulative']])->toBe([400.0, 150.0])
        ->and([$monthly[11]['month']->format('Y-m'), $monthly[11]['expenses'], $monthly[11]['cumulative']])->toBe(['2026-08', 40.0, 110.0]);
});

it('lists the internal movements apart, from both sides', function (): void {
    frLine(-2000.0, '2026-07-02', internal: true);
    $deposit = frCash(-30000, '2026-09-18 19:00:00');
    (new LinkCashDepositAction)(frLine(300.0, '2026-09-19'), $deposit);

    $internal = FinancialReport::for(FiscalYear::startingIn(2026))->internalMovements();

    expect(array_map(fn (array $movement): array => [$movement['date']->toDateString(), $movement['amount']], $internal))
        ->toBe([['2026-07-02', -2000.0], ['2026-09-18', -300.0], ['2026-09-19', 300.0]]);
});

it('keeps a journal of every movement, with its poste, its state and what justifies it', function (): void {
    $document = SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['amount' => 300.0, 'date' => '2026-02-01']);
    $hall = frLine(-300.0, '2026-02-10');
    (new LinkSupportingDocument)($document, $hall);
    $expenseReport = ExpenseReport::factory()->accepted(45.5)->create(['category' => ExpenseCategory::Travel, 'amount' => 45.5]);
    frAllocate(frLine(-45.5, '2026-03-20'), (new OpenRefundAction)->forPayable($expenseReport, 45.5), 45.5);
    frCash(500, '2026-01-05 10:00:00');

    $journal = FinancialReport::for(FiscalYear::startingIn(2026))->journal();

    expect(array_map(fn (array $row): array => [$row['date']->toDateString(), $row['kind'], $row['amount'], $row['closure']->value, $row['postes'], $row['documents'], $row['expense_reports']], $journal))
        ->toBe([
            ['2026-01-05', 'cash', 5.0, 'to_process', ['income:uncategorised'], [], []],
            ['2026-02-10', 'bank', -300.0, 'justified', ['expense:hall'], [sprintf('P-2026-%04d', $document->id)], []],
            ['2026-03-20', 'bank', -45.5, 'reconciled', ['expense:travel'], [], [$expenseReport->id]],
        ]);
});
