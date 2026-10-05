<?php

declare(strict_types=1);

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
use App\Domains\Shared\Enums\SupportingDocumentState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

const SD_SCREEN = 'pages::club-admin.treasury.supporting-documents';

beforeEach(function (): void {
    Storage::fake('local');
});

function sdScreenAs(Role ...$roles): Testable
{
    return Livewire::actingAs(User::factory()->withRole(...$roles)->create())->test(SD_SCREEN);
}

/** @return list<int> */
function sdListed(Testable $screen): array
{
    return collect($screen->instance()->documents()->items())->pluck('id')->sort()->values()->all();
}

it('is reachable by whoever reads the bank lines, and nobody else', function (): void {
    $this->actingAs(User::factory()->withRole(Role::COMMITTEE)->create())
        ->get(route('admin.treasury.supporting-documents'))->assertOk();

    $this->actingAs(User::factory()->create())
        ->get(route('admin.treasury.supporting-documents'))->assertForbidden();
});

it('lets the treasurer file a document with its file', function (): void {
    $screen = sdScreenAs(Role::TREASURY)
        ->call('create')
        ->set('documentCategory', 'expense:' . ExpenseCategory::Federation->value)
        ->set('documentDate', '2026-09-03')
        ->set('documentAmount', '1 240,50')
        ->set('documentCounterparty', 'AFTT')
        ->set('documentLabel', 'Affiliation du club 2026-2027')
        ->set('documentFiles', [UploadedFile::fake()->create('aftt.pdf', 30, 'application/pdf')])
        ->call('save')
        ->assertHasNoErrors();

    $document = SupportingDocument::sole();

    expect($document->expense_category)->toBe(ExpenseCategory::Federation)
        ->and($document->amount)->toBe(1240.5)
        ->and($document->files)->toHaveCount(1)
        ->and(sdListed($screen))->toBe([$document->id]);
});

it('refuses to file a document without a file', function (): void {
    sdScreenAs(Role::TREASURY)
        ->call('create')
        ->set('documentCategory', 'expense:' . ExpenseCategory::Other->value)
        ->set('documentDate', '2026-09-03')
        ->set('documentAmount', '10')
        ->set('documentCounterparty', 'X')
        ->set('documentLabel', 'Y')
        ->call('save')
        ->assertHasErrors('documentFiles');

    expect(SupportingDocument::count())->toBe(0);
});

it('filters by state, category and financial year', function (): void {
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();

    $hallLastYear = SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['date' => '2025-06-30']);
    $hallThisYear = SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['date' => '2025-07-01']);
    $subsidy = SupportingDocument::factory()->income(IncomeCategory::Subsidies)->create(['date' => '2025-09-01']);
    (new LinkSupportingDocument)($subsidy, Transaction::create(['date' => '2025-09-10', 'description' => 'SUBSIDE', 'amount' => 800]));

    $screen = sdScreenAs(Role::TREASURY);

    expect(sdListed($screen->set('fiscalYear', 2025)))->toBe([$hallThisYear->id, $subsidy->id])
        ->and(sdListed($screen->set('categoryFilter', 'expense:hall')))->toBe([$hallThisYear->id])
        ->and(sdListed($screen->set('categoryFilter', '')->set('fiscalYear', null)->set('stateFilter', SupportingDocumentState::ToSettle->value)))->toBe([$hallLastYear->id, $hallThisYear->id]);
});

it('suggests the bank line of the same amount, and links it on a click', function (): void {
    $document = SupportingDocument::factory()->expense(ExpenseCategory::SportsEquipment)->create([
        'date' => '2026-09-01', 'amount' => 89.4, 'counterparty' => 'Tibhar',
    ]);
    $match = Transaction::create(['date' => '2026-09-20', 'description' => 'ACHAT BALLES', 'amount' => -89.4, 'counterparty_name' => 'TIBHAR BELGIUM']);
    Transaction::create(['date' => '2026-12-20', 'description' => 'TROP TARD', 'amount' => -89.4]);
    Transaction::create(['date' => '2026-09-20', 'description' => 'AUTRE MONTANT', 'amount' => -89.5]);
    Transaction::create(['date' => '2026-09-20', 'description' => 'EN SENS INVERSE', 'amount' => 89.4]);

    $screen = sdScreenAs(Role::TREASURY)->call('show', $document->id);

    expect($screen->instance()->transactionSuggestions()->modelKeys())->toBe([$match->id]);

    $screen->call('linkTransaction', $match->id);

    expect($document->fresh()->state())->toBe(SupportingDocumentState::Settled)
        ->and($match->fresh()->isJustified())->toBeTrue();
});

it('finds a line the suggestions miss through the search', function (): void {
    $document = SupportingDocument::factory()->create(['amount' => 300]);
    $firstHalf = Transaction::create(['date' => '2026-09-20', 'description' => 'ACOMPTE', 'amount' => -150, 'counterparty_name' => 'Imprimerie Hayez']);

    $screen = sdScreenAs(Role::TREASURY)->call('show', $document->id)->set('linkSearch', 'hayez');

    expect($screen->instance()->transactionSearchResults()->modelKeys())->toBe([$firstHalf->id]);
});

it('pays a document from the till', function (): void {
    $register = CashRegister::create(['name' => 'Caisse du club']);
    $document = SupportingDocument::factory()->expense(ExpenseCategory::Bar)->create(['amount' => 23.5]);

    sdScreenAs(Role::TREASURY, Role::CASH_REGISTER)
        ->call('show', $document->id)
        ->set('cashRegisterId', $register->id)
        ->call('payInCash');

    $entry = CashRegisterEntry::sole();

    expect($entry->amount)->toBe(-2350)
        ->and($entry->cash_register_id)->toBe($register->id)
        ->and($document->fresh()->state())->toBe(SupportingDocumentState::Settled);
});

it('refuses to delete a document that justifies a movement', function (): void {
    $document = SupportingDocument::factory()->create();
    (new LinkSupportingDocument)($document, Transaction::create(['date' => '2026-09-20', 'description' => 'X', 'amount' => -10]));

    sdScreenAs(Role::TREASURY)->call('show', $document->id)->call('confirmDelete');

    expect(SupportingDocument::find($document->id))->not->toBeNull();
});

it('lets the committee read a document, and write nothing', function (): void {
    $document = SupportingDocument::factory()->create(['counterparty' => 'Complexe sportif de Blocry']);
    $line = Transaction::create(['date' => now()->toDateString(), 'description' => 'X', 'amount' => -10]);

    $screen = sdScreenAs(Role::COMMITTEE)
        ->call('show', $document->id)
        ->assertSee('Complexe sportif de Blocry')
        ->assertDontSeeHtml('wire:click="create"')
        ->assertDontSee(__('Link bank transactions'));

    $screen->call('linkTransaction', $line->id)->assertForbidden();
});

it('guards every write against a reader who crafts the call', function (string $method): void {
    $document = SupportingDocument::factory()->create();

    sdScreenAs(Role::COMMITTEE)->call('show', $document->id)->call($method)->assertForbidden();
})->with(['create', 'edit', 'openDelete', 'confirmDelete', 'payInCash', 'save']);

it('offers in its year picker the running year and the years money moved in only', function (): void {
    $this->travelTo('2026-09-30 10:00:00');
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();
    Transaction::create(['date' => '2024-03-14', 'description' => 'VIREMENT', 'amount' => 25]);

    expect(sdScreenAs(Role::TREASURY)->viewData('yearOptions'))->toBe([
        ['id' => 2026, 'name' => '2026-2027'],
        ['id' => 2023, 'name' => '2023-2024'],
    ]);
});
