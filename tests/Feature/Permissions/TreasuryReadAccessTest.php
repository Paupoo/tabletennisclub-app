<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

pest()->group('treasury', 'permissions');

/*
| The club's accounts, read by the committee — transactions, fines and the
| cash register included, since the treasurer is not the only one who answers
| for them. Reconciling, fining and recording cash stay with their délégations.
*/

beforeEach(function (): void {
    makeActiveSeason();
    $this->reader = User::factory()->isCommitteeMember()->create();
});

it('opens the four treasury screens to the committee', function (string $routeName): void {
    $this->actingAs($this->reader)->get(route($routeName))->assertOk();
})->with([
    'admin.treasury.payments',
    'admin.treasury.transactions',
    'admin.treasury.fines',
    'admin.treasury.cash',
]);

it('offers a reader no bank import and no deletion', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.treasury.transactions')
        ->assertDontSee("\$set('importModal', true)", escape: false)
        ->assertDontSee('openConfirmDeleteModal');
});

it('refuses a reader the deletion of bank lines', function (): void {
    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.treasury.transactions')
        ->call('openConfirmDeleteModal')
        ->assertForbidden();
});

it('shows a reader the fines without a way to issue or cancel one', function (): void {
    $fine = Fine::factory()->create();

    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.treasury.fines')
        ->assertSee($fine->user->last_name)
        ->assertDontSee('openFineDrawer')
        ->assertDontSee('confirmCancel(' . $fine->id . ')');
});

it('shows a reader the cash register without a way to record in it', function (): void {
    CashRegister::create(['name' => 'Caisse du bar']);

    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.treasury.cash-register')
        ->assertSee('Caisse du bar')
        ->assertDontSee('openManualEntry')
        ->assertDontSee("\$set('createRegisterModal', true)", escape: false);
});

it('refuses a reader a manual cash entry', function (string $method): void {
    CashRegister::create(['name' => 'Caisse du bar']);

    Livewire::actingAs($this->reader)
        ->test('pages::club-admin.treasury.cash-register')
        ->set('entryAmount', 50)
        ->set('entryReason', 'manual')
        ->call($method)
        ->assertForbidden();

    expect(CashRegisterEntry::count())->toBe(0);
})->with(['openManualEntry', 'saveManualEntry']);
