<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\ExpenseCategory;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Trésorerie — une seule caisse pour tout le club
|--------------------------------------------------------------------------
|
| Le club n'a qu'un tiroir. L'app laissait en ouvrir plusieurs, en retirer, en
| choisir une à chaque saisie, alors que les tournois prenaient déjà « la
| première venue ». Le bar, les tournois, les pièces et le trésorier écrivent
| désormais tous dans la même caisse, sans qu'on ait à la désigner.
|
| Et le tiroir contient des centimes : une consommation coûte 2,50 €, une
| saisie à l'euro entier obligeait à arrondir un dépôt.
|
*/

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

function tillScreen(User $user): Testable
{
    return Livewire::actingAs($user)->test('pages::club-admin.treasury.cash-register');
}

it('offers no way to open, pick or retire another till', function (): void {
    CashRegister::create(['name' => 'Caisse principale']);

    tillScreen($this->admin)
        ->assertSee('Caisse principale')
        ->assertDontSee(__('New register'))
        ->assertDontSee(__('Show retired registers'))
        ->assertDontSee('openRetireRegister');
});

it('records a manual entry in the club till, cents included', function (): void {
    $till = CashRegister::create(['name' => 'Caisse principale']);

    tillScreen($this->admin)
        ->call('openManualEntry')
        ->set('entryAmount', '-12.35')
        ->set('entryNotes', 'Monnaie pour le tournoi')
        ->call('saveManualEntry')
        ->assertHasNoErrors();

    $entry = CashRegisterEntry::sole();

    expect($entry->cash_register_id)->toBe($till->id)
        ->and($entry->amount)->toBe(-1235);
});

it('reads a comma as the decimal separator', function (): void {
    CashRegister::create(['name' => 'Caisse principale']);

    tillScreen($this->admin)
        ->set('entryAmount', '7,50')
        ->call('saveManualEntry')
        ->assertHasNoErrors();

    expect(CashRegisterEntry::sole()->amount)->toBe(750);
});

it('refuses an amount of nothing, or finer than a cent', function (string $amount): void {
    CashRegister::create(['name' => 'Caisse principale']);

    tillScreen($this->admin)
        ->set('entryAmount', $amount)
        ->call('saveManualEntry')
        ->assertHasErrors('entryAmount');

    expect(CashRegisterEntry::count())->toBe(0);
})->with(['0', '0.00', '1.234', 'abc', '']);

it('opens the club till for the first manual entry', function (): void {
    tillScreen($this->admin)
        ->set('entryAmount', '100')
        ->call('saveManualEntry')
        ->assertHasNoErrors();

    expect(CashRegister::count())->toBe(1)
        ->and(CashRegisterEntry::sole()->amount)->toBe(10_000);
});

it('pays a supporting document from the club till without asking which one', function (): void {
    Storage::fake('local');
    Club::factory()->ownClub()->create();
    $till = CashRegister::create(['name' => 'Caisse principale']);
    $document = SupportingDocument::factory()->create(['amount' => 12.5, 'expense_category' => ExpenseCategory::Other, 'income_category' => null]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.treasury.supporting-documents')
        ->call('show', $document->id)
        ->assertDontSee('cashRegisterId')
        ->call('payInCash')
        ->assertHasNoErrors();

    expect(CashRegisterEntry::sole()->cash_register_id)->toBe($till->id)
        ->and(CashRegisterEntry::sole()->amount)->toBe(-1250);
});
