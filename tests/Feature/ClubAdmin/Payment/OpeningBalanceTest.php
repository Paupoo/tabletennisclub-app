<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\DeclareOpeningBalanceAction;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\MovementClosure;
use App\Domains\Shared\Enums\Role;
use App\Domains\Shared\ValueObjects\FiscalYear;
use App\Services\ClubAdmin\Dashboard\PendingTasks;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Trésorerie — un mouvement d'origine inconnue doit être tranché
|--------------------------------------------------------------------------
|
| Les 100 € posés dans la caisse au début de la saison n'étaient reliés à
| rien : le rapport financier les comptait comme une recette « à traiter »,
| de l'argent que le club n'avait pas gagné. Deux portes pour en sortir :
|
| - « Retrait à la banque » : le lien à la ligne de l'extrait, qui existait ;
| - « Solde d'ouverture » : ce que le tiroir contenait avant d'être suivi.
|   Hors recettes, et seulement pour le premier mouvement d'une caisse — sans
|   quoi n'importe quel surplus pourrait un jour en sortir des recettes.
|
| Tant que personne n'a tranché, le mouvement reste dans les tâches.
|
*/

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();

    $this->treasurer = User::factory()->withRole(Role::TREASURY)->create();
    $this->till = CashRegister::create(['name' => 'Caisse principale']);
    $this->float = CashRegisterEntry::create([
        'cash_register_id' => $this->till->id,
        'amount' => 10_000,
        'reason' => 'manual',
        'notes' => '75 € en billets, 25 € en pièces',
        'recorded_by_id' => $this->treasurer->id,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('takes the opening balance out of the income, and out of what is left to process', function (): void {
    (new DeclareOpeningBalanceAction)($this->float);

    $report = FinancialReport::for(FiscalYear::startingIn(2026));
    $row = collect($report->journal())->firstWhere('id', $this->float->id);

    expect($this->float->fresh()->isOpeningBalance())->toBeTrue()
        ->and($this->float->fresh()->isClosed())->toBeTrue()
        ->and($report->income())->toBe(0.0)
        ->and($row['closure'])->toBe(MovementClosure::OpeningBalance)
        ->and($this->till->currentBalance())->toBe(10_000);
});

it('only accepts the first movement of the till', function (): void {
    $this->travel(1)->hour();
    $later = CashRegisterEntry::create([
        'cash_register_id' => $this->till->id,
        'amount' => 1_000,
        'reason' => 'manual',
        'recorded_by_id' => $this->treasurer->id,
    ]);

    expect(fn () => (new DeclareOpeningBalanceAction)($later))->toThrow(DomainException::class);
    expect($later->fresh()->isOpeningBalance())->toBeFalse();
});

it('refuses money that went out, or that something already explains', function (): void {
    $this->float->update(['amount' => -10_000]);

    expect(fn () => (new DeclareOpeningBalanceAction)($this->float))->toThrow(DomainException::class);

    $this->float->update(['amount' => 10_000]);
    $this->float->payable()->associate(BarOrder::create(['created_by' => $this->treasurer->id, 'total_price' => 10_000, 'is_paid' => 1]))->save();

    expect(fn () => (new DeclareOpeningBalanceAction)($this->float))->toThrow(DomainException::class);
});

it('can be undone, and the movement is back to process', function (): void {
    (new DeclareOpeningBalanceAction)($this->float);
    (new DeclareOpeningBalanceAction)->undo($this->float->fresh());

    expect($this->float->fresh()->isOpeningBalance())->toBeFalse()
        ->and($this->float->fresh()->isClosed())->toBeFalse();
});

it('offers the two ways out on the screen, and declares the opening balance from there', function (): void {
    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.cash-register')
        ->assertSee(__('It is a deposit to or from the bank'))
        ->assertSee(__('It is the opening balance'))
        ->call('declareOpeningBalance', $this->float->id)
        ->assertHasNoErrors()
        ->assertSee(__('Opening balance'));

    expect($this->float->fresh()->isOpeningBalance())->toBeTrue();
});

it('refuses the opening balance to whoever cannot file the treasury', function (): void {
    Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test('pages::club-admin.treasury.cash-register')
        ->call('declareOpeningBalance', $this->float->id)
        ->assertForbidden();

    expect($this->float->fresh()->isOpeningBalance())->toBeFalse();
});

it('keeps an unexplained movement among the tasks until someone decides', function (): void {
    expect((new PendingTasks)->for($this->treasurer)['cash_movements']->count ?? 0)->toBe(1);

    (new DeclareOpeningBalanceAction)($this->float);
    // The tasks are kept for the request: a new one reads them again.
    app()->instance('request', Request::create('/'));

    expect((new PendingTasks)->for($this->treasurer)['cash_movements']->count ?? 0)->toBe(0)
        ->and((new PendingTasks)->for(User::factory()->isCommitteeMember()->create()))->not->toHaveKey('cash_movements');
});
