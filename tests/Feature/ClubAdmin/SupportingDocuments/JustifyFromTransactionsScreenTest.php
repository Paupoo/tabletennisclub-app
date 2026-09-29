<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
});

function jfScreen(Role $role = Role::TREASURY): Testable
{
    return Livewire::actingAs(User::factory()->withRole($role)->create())->test('pages::club-admin.treasury.transactions');
}

function jfBlocryDebit(): Transaction
{
    return Transaction::create([
        'date' => '2026-09-05',
        'description' => 'LOYER SALLE T3',
        'amount' => -1250.0,
        'counterparty_name' => 'COMPLEXE SPORTIF BLOCRY',
    ]);
}

it('prefills a new document from the line, and links it on filing', function (): void {
    $debit = jfBlocryDebit();

    $screen = jfScreen()->call('openJustification', $debit->id)
        ->assertSet('documentDate', '2026-09-05')
        ->assertSet('documentAmount', '1250.00')
        ->assertSet('documentCounterparty', 'COMPLEXE SPORTIF BLOCRY');

    $screen->set('documentCategory', 'expense:' . ExpenseCategory::Hall->value)
        ->set('documentLabel', 'Location salle — 3e trimestre')
        ->set('documentFiles', [UploadedFile::fake()->create('facture.pdf', 40, 'application/pdf')])
        ->call('createAndLinkDocument')
        ->assertHasNoErrors();

    $document = SupportingDocument::sole();

    expect($document->expense_category)->toBe(ExpenseCategory::Hall)
        ->and($document->amount)->toBe(1250.0)
        ->and($debit->fresh()->isJustified())->toBeTrue();
});

it('suggests the documents to settle of the same amount and direction, the counterparty first', function (): void {
    $debit = jfBlocryDebit();
    $other = SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['date' => '2026-09-01', 'amount' => 1250, 'counterparty' => 'Salle privée']);
    $blocry = SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['date' => '2026-08-01', 'amount' => 1250, 'counterparty' => 'Complexe sportif Blocry']);
    SupportingDocument::factory()->income(IncomeCategory::Other)->create(['date' => '2026-09-01', 'amount' => 1250]);
    SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['date' => '2026-06-01', 'amount' => 1250]);
    $alreadyPaid = SupportingDocument::factory()->expense(ExpenseCategory::Hall)->create(['date' => '2026-09-02', 'amount' => 1250]);
    (new LinkSupportingDocument)($alreadyPaid, Transaction::create(['date' => '2026-09-02', 'description' => 'X', 'amount' => -1250]));

    $screen = jfScreen()->call('openJustification', $debit->id);

    expect($screen->instance()->documentSuggestions()->modelKeys())->toBe([$blocry->id, $other->id]);
});

it('links a suggested document, and unlinks it', function (): void {
    $debit = jfBlocryDebit();
    $document = SupportingDocument::factory()->create(['amount' => 1250, 'date' => '2026-09-01']);

    $screen = jfScreen()->call('openJustification', $debit->id)->call('linkDocument', $document->id);
    expect($debit->fresh()->isJustified())->toBeTrue();

    $screen->call('unlinkDocument', $document->id);
    expect($debit->fresh()->isJustified())->toBeFalse();
});

it('shows a reader what justifies a line, and refuses them any link', function (): void {
    $debit = jfBlocryDebit();
    $document = SupportingDocument::factory()->create(['counterparty' => 'Complexe sportif de Blocry', 'amount' => 1250]);
    (new LinkSupportingDocument)($document, $debit);

    jfScreen(Role::COMMITTEE)
        ->call('openJustification', $debit->id)
        ->assertSee($document->reference())
        ->assertDontSee(__('Link an existing document'))
        ->call('unlinkDocument', $document->id)
        ->assertForbidden();
});
