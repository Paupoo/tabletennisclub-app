<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\Role;
use App\Support\Charts\ChartPalette;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * « Rapport financier »: the committee, the accounts auditors and the
 * treasury read the year's accounts. Nobody writes anything here.
 */
beforeEach(function (): void {
    Storage::fake('local');
    Carbon::setTestNow('2026-09-30 10:00:00');
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 1]);
    Club::forgetOwnClub();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function frsJustified(float $amount, string $date, ExpenseCategory|IncomeCategory $category): void
{
    $state = $category instanceof ExpenseCategory
        ? ['expense_category' => $category, 'income_category' => null]
        : ['expense_category' => null, 'income_category' => $category];

    (new LinkSupportingDocument)(
        SupportingDocument::factory()->create([...$state, 'amount' => abs($amount), 'date' => $date]),
        Transaction::create(['date' => $date, 'description' => 'VIREMENT', 'amount' => $amount]),
    );
}

function frsScreen(?User $user = null): Testable
{
    return Livewire::actingAs($user ?? User::factory()->withRole(Role::TREASURY)->create())
        ->test('pages::club-admin.treasury.report');
}

it('opens the report to the committee, the accounts auditors and the treasury', function (Role $role): void {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->get(route('admin.treasury.report'))
        ->assertOk()
        ->assertSee(__('Financial report'));
})->with([Role::COMMITTEE, Role::ACCOUNTS_AUDIT, Role::TREASURY]);

it('keeps the report away from whoever only holds the till', function (): void {
    $this->actingAs(User::factory()->withRole(Role::CASH_REGISTER)->create())
        ->get(route('admin.treasury.report'))
        ->assertForbidden();
});

it('puts the report first in the treasury menu', function (): void {
    $this->actingAs(User::factory()->withRole(Role::TREASURY)->create())
        ->get(route('admin.treasury.transactions'))
        ->assertSeeInOrder([route('admin.treasury.report'), route('admin.treasury.payments')], escape: false);
});

it('shows the year\'s flows next to the year before, judged for the club', function (): void {
    frsJustified(-1400.0, '2026-01-05', ExpenseCategory::Hall);
    frsJustified(500.0, '2026-04-08', IncomeCategory::Subsidies);
    frsJustified(-1250.0, '2025-01-05', ExpenseCategory::Hall);

    $html = frsScreen()->html();

    // Expenses 1 400 € against 1 250 €: +12 %, and spending more is bad news.
    expect($html)->toContain('1 400,00 €')
        ->toContain('+12 % ' . __('vs :year', ['year' => '2025']))
        ->toContain('data-change="up"')
        ->toContain('−900,00 €')
        ->toContain(__('Nothing to compare with in :year', ['year' => '2025']));
});

it('moves to another financial year from the year navigation', function (): void {
    frsJustified(-1250.0, '2025-01-05', ExpenseCategory::Hall);

    frsScreen()
        ->set('fiscalYear', 2025)
        ->assertSee(__('Financial year :year, compared with :previous', ['year' => '2025', 'previous' => '2024']))
        ->assertSee('1 250,00 €');
});

it('draws the four charts, each titled and described for a screen reader', function (): void {
    frsJustified(-1400.0, '2026-01-05', ExpenseCategory::Hall);
    frsJustified(500.0, '2026-04-08', IncomeCategory::Subsidies);

    $html = frsScreen()->html();

    foreach (['chart-monthly', 'chart-expenses', 'chart-income', 'chart-closure'] as $chart) {
        expect($html)->toContain('aria-labelledby="' . $chart . '-title ' . $chart . '-desc"');
    }

    expect($html)->toContain('tabindex="0"');
});

it('lists the documents, expense reports and website payments of the year', function (): void {
    frsJustified(-1400.0, '2026-01-05', ExpenseCategory::Hall);
    frsJustified(-99.0, '2025-06-05', ExpenseCategory::Hall);

    $html = frsScreen()->set('tab', 'pieces')->html();

    expect(substr_count($html, 'wire:key="piece-document-'))->toBe(1)
        ->and($html)->toContain(__('No expense report paid in this year.'))
        ->and($html)->toContain(__('No website payment reconciled in this year.'));
});

it('renders a chart mPDF can read: plain colours, no script, when asked to', function (): void {
    $html = Blade::render(
        '<x-charts.paired-bars id="print" title="T" :rows="$rows" current-label="2026" previous-label="2025" :palette="$palette" :interactive="false" />',
        ['rows' => [['label' => 'Salle', 'current' => 1400.0, 'previous' => 1250.0]], 'palette' => ChartPalette::print()],
    );

    expect($html)->toContain('fill="#2a78d6"')
        ->not->toContain('var(--chart')
        ->not->toContain('x-data')
        ->not->toContain('tabindex')
        ->toContain('<title>Salle — 2026 : 1 400,00 € (+12 %) · 2025 : 1 250,00 €</title>');
});
