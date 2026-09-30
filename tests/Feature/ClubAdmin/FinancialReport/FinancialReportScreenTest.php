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
use Illuminate\Support\Str;
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
    // After the 30th of September 2025: not due yet, this year.
    frsJustified(-700.0, '2025-11-05', ExpenseCategory::Hall);

    $html = frsScreen()->html();
    $sameDate = __(':year at the same date', ['year' => '2025']);

    // Expenses 1 400 € against the 1 250 € of 2025 until the 30th of
    // September: +12 %, and spending more is bad news.
    expect($html)->toContain('1 400,00 €')
        ->toContain('+12 % ' . __('vs :year', ['year' => $sameDate]))
        ->toContain('data-change="up"')
        ->toContain('−900,00 €')
        ->toContain(__('Nothing to compare with in :year', ['year' => $sameDate]))
        ->not->toContain('1 950,00 €');
});

it('compares a closed year with the whole year before it', function (): void {
    frsJustified(-1250.0, '2025-01-05', ExpenseCategory::Hall);
    frsJustified(-700.0, '2024-11-05', ExpenseCategory::Hall);
    frsJustified(-100.0, '2024-01-05', ExpenseCategory::Hall);

    // 1 250 € against the 800 € of the whole of 2024: +56 %.
    frsScreen()
        ->set('fiscalYear', 2025)
        ->assertSee('+56 % ' . __('vs :year', ['year' => '2024']))
        ->assertDontSee(__(':year at the same date', ['year' => '2024']));
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

/*
 * « 97 % » then « 90,6 % des mouvements (126 sur 139), en montant ci-dessus »
 * read as jargon: one percentage per tile, and the rest in words.
 */
it('says in plain words how much of the money is accounted for, and what is left to process', function (): void {
    frsJustified(-300.0, '2026-02-10', ExpenseCategory::Hall);
    frsJustified(-500.0, '2026-03-10', ExpenseCategory::Hall);
    frsJustified(-100.0, '2026-04-10', ExpenseCategory::Hall);
    Transaction::create(['date' => '2026-05-02', 'description' => 'VIREMENT', 'amount' => 100.0]);

    $html = frsScreen()->html();
    $tile = Str::before(Str::after($html, 'data-tile="accounted-for"'), 'data-stat-card');

    // 900 € of 1 000 € accounted for: 90 %.
    expect($tile)->toContain(__('Money accounted for'))
        ->toContain('90 %')
        ->toContain('3 mouvements sur 4 ont leur justificatif')
        ->toContain('1 reste à traiter')
        ->toContain(e(route('admin.treasury.transactions', ['state' => 'to_process', 'from' => '2026-01-01', 'to' => '2026-12-31'])))
        ->toContain(e(__('A movement is accounted for when it is reconciled with a website payment, covered by a supporting document, or is an internal transfer.')))
        ->not->toContain('90,0 %')
        ->not->toContain('75 %');
});

it('opens the transactions still to process from the report, for the year shown', function (): void {
    Transaction::create(['date' => '2026-05-02', 'description' => 'VIREMENT', 'counterparty_name' => 'Non traité', 'amount' => 100.0]);
    Transaction::create(['date' => '2025-05-02', 'description' => 'VIREMENT', 'counterparty_name' => 'An dernier', 'amount' => 100.0]);
    Transaction::create(['date' => '2026-06-02', 'description' => 'VIREMENT', 'counterparty_name' => 'Interne', 'amount' => -100.0, 'is_internal' => true]);

    $this->actingAs(User::factory()->withRole(Role::TREASURY)->create())
        ->get(route('admin.treasury.transactions', ['state' => 'to_process', 'from' => '2026-01-01', 'to' => '2026-12-31']))
        ->assertOk()
        ->assertSee('Non traité')
        ->assertDontSee('An dernier')
        ->assertDontSee('Interne');
});
