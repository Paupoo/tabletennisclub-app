<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\DeclareOpeningBalanceAction;
use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Finance\Services\FinancialPosition;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Finance\Services\FinancialReportFigures;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Rapport financier — la part du virement et la part des espèces
|--------------------------------------------------------------------------
|
| Dans toutes les recettes, combien est passé par la banque et combien par
| la caisse. Un QR est un virement comme un autre sur l'extrait : « virement »
| veut dire « ligne bancaire », « espèces » veut dire « mouvement de caisse ».
|
| Un remboursement ou un manque de caisse se déduit du canal par lequel
| l'argent est passé : pour chaque poste, virement + espèces = total.
| Les dépenses ne sont pas ventilées, ni l'exercice de comparaison.
|
*/

beforeEach(function (): void {
    Storage::fake('local');
    Carbon::setTestNow('2026-09-30 10:00:00');
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 1]);
    Club::forgetOwnClub();

    $this->till = CashRegister::create(['name' => 'Caisse principale']);
    $this->recorder = User::factory()->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function ibcBank(float $amount, string $date, ?IncomeCategory $category = null): Transaction
{
    $line = Transaction::create(['date' => $date, 'description' => 'VIREMENT', 'amount' => $amount]);

    if ($category !== null) {
        (new LinkSupportingDocument)(SupportingDocument::factory()->create([
            'income_category' => $category, 'expense_category' => null, 'amount' => abs($amount), 'date' => $date,
        ]), $line);
    }

    return $line;
}

function ibcCash(int $cents, string $at, array $attributes = []): CashRegisterEntry
{
    $entry = CashRegisterEntry::create([
        'cash_register_id' => CashRegister::club()->id,
        'amount' => $cents,
        'reason' => 'manual',
        'recorded_by_id' => User::query()->value('id'),
        ...$attributes,
    ]);
    $entry->forceFill(['created_at' => $at])->saveQuietly();

    return $entry;
}

function ibcBarSale(int $cents, string $at): CashRegisterEntry
{
    $order = BarOrder::create(['created_by' => User::query()->value('id'), 'total_price' => $cents, 'is_paid' => 1, 'payment_method' => 'cash']);

    return ibcCash($cents, $at, ['reason' => 'bar_sale', 'payable_type' => BarOrder::class, 'payable_id' => $order->id]);
}

/**
 * Subsidies 500 € by transfer; the bar 30 € by transfer and 80 € in cash,
 * less 5 € found missing at a count: 530 € by transfer, 75 € in cash.
 */
function ibcYear(): void
{
    ibcBank(500.0, '2026-03-05', IncomeCategory::Subsidies);
    ibcBank(30.0, '2026-04-10', IncomeCategory::Bar);
    ibcBarSale(8_000, '2026-05-02 20:00:00');
    ibcCash(-500, '2026-05-03 10:00:00', ['reason' => CashRegisterEntry::DIFFERENCE_REASON, 'category' => IncomeCategory::Bar->value]);
}

it('splits the income between transfers and cash, poste by poste, net of what went back', function (): void {
    ibcYear();

    $report = FinancialReport::for(FiscalYear::startingIn(2026));

    expect($report->incomeByChannel())->toBe(['bank' => 530.0, 'cash' => 75.0])
        ->and($report->incomeByCategoryAndChannel())->toBe([
            'bar_income' => ['bank' => 30.0, 'cash' => 75.0],
            'subsidies' => ['bank' => 500.0, 'cash' => 0.0],
        ])
        ->and($report->income())->toBe(605.0);
});

it('keeps the internal movements, the opening balance and the expenses out of it', function (): void {
    ibcCash(10_000, '2026-01-01 08:00:00');
    (new DeclareOpeningBalanceAction)(CashRegisterEntry::sole());
    (new LinkCashDepositAction)(ibcBank(-50.0, '2026-02-02'), ibcCash(5_000, '2026-02-01 18:00:00'));
    (new LinkSupportingDocument)(
        SupportingDocument::factory()->create(['expense_category' => ExpenseCategory::Hall, 'income_category' => null, 'amount' => 40.0]),
        ibcBank(-40.0, '2026-03-01'),
    );
    ibcBank(25.0, '2026-04-01');

    $report = FinancialReport::for(FiscalYear::startingIn(2026));

    expect($report->incomeByChannel())->toBe(['bank' => 25.0, 'cash' => 0.0])
        ->and($report->incomeByCategoryAndChannel())->toBe([FinancialReport::UNCATEGORISED => ['bank' => 25.0, 'cash' => 0.0]]);
});

it('hands the split to the screen and the PDF, for the year shown only', function (): void {
    ibcYear();
    ibcBarSale(4_000, '2025-05-02 20:00:00');

    $report = FinancialReport::for(FiscalYear::startingIn(2026));
    $channels = FinancialReportFigures::of($report, FinancialReport::previousFor($report->year()), new FinancialPosition)['incomeChannels'];

    expect($channels['rows'])->toBe([
        ['key' => 'subsidies', 'label' => IncomeCategory::Subsidies->label(), 'bank' => 500.0, 'cash' => 0.0, 'uncategorised' => false],
        ['key' => 'bar_income', 'label' => IncomeCategory::Bar->label(), 'bank' => 30.0, 'cash' => 75.0, 'uncategorised' => false],
    ])
        ->and([$channels['bank'], $channels['cash'], $channels['bank_share'], $channels['cash_share']])->toBe([530.0, 75.0, 88, 12]);
});

it('shows the split in a block of its own on the screen, leaving the income tile and table alone', function (): void {
    ibcYear();

    $html = Livewire::actingAs(User::factory()->withRole(Role::TREASURY)->create())
        ->test('pages::club-admin.treasury.report')
        ->html();

    $block = Str::between($html, 'data-income-channels', '</table>');
    $posteTables = array_map(fn (string $part): string => Str::before($part, '</table>'), array_slice(explode('data-poste-table', $html), 1));

    expect($block)->toContain(__('By transfer'))
        ->toContain(__('In cash'))
        ->toContain('530,00 €')
        ->toContain('88 %')
        ->toContain('75,00 €')
        ->toContain('12 %')
        ->and($posteTables)->toHaveCount(2)
        ->and(implode('', $posteTables))->not->toContain(__('By transfer'))
        ->and(substr_count($html, 'data-income-channels="'))->toBe(1);
});

it('prints the same block in the PDF', function (): void {
    ibcYear();

    $report = FinancialReport::for(FiscalYear::startingIn(2026));
    $html = View::make('financial-export.report', [
        ...FinancialReportFigures::of($report, FinancialReport::previousFor($report->year()), new FinancialPosition),
        'clubName' => 'CTT', 'exportedAt' => now(), 'exportedBy' => 'Gilles', 'filters' => [],
    ])->render();

    expect($html)->toContain(__('Income by transfer and in cash'))
        ->toContain('530,00 € (88 %)')
        ->toContain('75,00 € (12 %)');
});
