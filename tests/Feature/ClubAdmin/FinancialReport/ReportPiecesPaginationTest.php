<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Rapport financier — les blocs de l'onglet « Pièces & exports » se paginent
|--------------------------------------------------------------------------
|
| Sur un exercice réel, les pièces, les notes de frais et les paiements du site
| se comptent par centaines : l'onglet devenait une page sans fin. Chaque bloc
| a ses propres pages, 25 lignes à la fois, pour qu'avancer dans l'un ne fasse
| pas perdre sa place dans l'autre.
|
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

function rppPiecesTab(): Testable
{
    return Livewire::actingAs(User::factory()->withRole(Role::TREASURY)->create())
        ->test('pages::club-admin.treasury.report')
        ->set('tab', 'pieces');
}

it('shows the supporting documents 25 at a time', function (): void {
    foreach (range(1, 27) as $day) {
        SupportingDocument::factory()->create(['date' => Carbon::parse('2026-01-01')->addDays($day)->toDateString(), 'counterparty' => sprintf('Fournisseur %02d', $day)]);
    }

    $tab = rppPiecesTab();
    $documents = $tab->instance()->documentsPage();

    expect($documents)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($documents->total())->toBe(27)
        ->and($documents->count())->toBe(25)
        ->and($documents->lastPage())->toBe(2);

    $tab->call('gotoPage', 2, 'documentsPage');

    expect($tab->instance()->documentsPage()->count())->toBe(2);
});

it('pages each block on its own', function (): void {
    foreach (range(1, 27) as $day) {
        SupportingDocument::factory()->create(['date' => Carbon::parse('2026-01-01')->addDays($day)->toDateString()]);
    }

    $tab = rppPiecesTab()->call('gotoPage', 2, 'documentsPage');

    expect($tab->instance()->documentsPage()->currentPage())->toBe(2)
        ->and($tab->instance()->expenseReportsPage()->currentPage())->toBe(1)
        ->and($tab->instance()->sitePaymentsPage()->currentPage())->toBe(1)
        ->and([$tab->instance()->expenseReportsPage()->getPageName(), $tab->instance()->sitePaymentsPage()->getPageName()])
        ->toBe(['expenseReportsPage', 'sitePaymentsPage']);
});

it('goes back to the first page when another year is shown', function (): void {
    foreach (range(1, 27) as $day) {
        SupportingDocument::factory()->create(['date' => Carbon::parse('2026-01-01')->addDays($day)->toDateString()]);
    }

    $tab = rppPiecesTab()->call('gotoPage', 2, 'documentsPage')->set('fiscalYear', 2025);

    expect($tab->instance()->documentsPage()->currentPage())->toBe(1);
});

it('prints the page links under a block that has more than one page', function (): void {
    foreach (range(1, 27) as $day) {
        SupportingDocument::factory()->create(['date' => Carbon::parse('2026-01-01')->addDays($day)->toDateString()]);
    }

    expect(rppPiecesTab()->html())->toContain("gotoPage(2, 'documentsPage')");
});
