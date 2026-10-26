<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\CountCashRegisterAction;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterCount;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Trésorerie — le contrôle de caisse
|--------------------------------------------------------------------------
|
| Le trésorier compte le tiroir et saisit ce qu'il a trouvé. L'app le compare
| au solde théorique et range elle-même l'écart :
|
| - un manque se répartit entre les catégories qui ont fait entrer du cash
|   depuis le contrôle précédent, au prorata des montants encaissés — le
|   contrôle borne la période, c'est lui qui « isole » un tournoi ;
| - un surplus va en « Autres recettes », sans répartition ;
| - un manque sans aucune recette dans la période va en « Autres dépenses ».
|
| Un contrôle juste s'enregistre aussi : il borne la période suivante.
|
*/

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();

    $this->treasurer = User::factory()->isAdmin()->create();
    $this->till = CashRegister::create(['name' => 'Caisse principale']);

    // Le fonds de caisse : ni recette du bar, ni d'un tournoi.
    tillMovement($this->till, 10_000);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function tillMovement(CashRegister $till, int $cents, ?Model $payable = null): CashRegisterEntry
{
    $entry = new CashRegisterEntry([
        'cash_register_id' => $till->id,
        'amount' => $cents,
        'reason' => 'manual',
        'recorded_by_id' => User::query()->value('id'),
    ]);

    if ($payable !== null) {
        $entry->payable()->associate($payable);
    }

    $entry->save();

    return $entry;
}

function barSale(CashRegister $till, int $cents): CashRegisterEntry
{
    return tillMovement($till, $cents, BarOrder::create([
        'created_by' => User::query()->value('id'),
        'total_price' => $cents,
        'is_paid' => 1,
        'payment_method' => 'cash',
    ]));
}

function countTill(CashRegister $till, int $countedCents): CashRegisterCount
{
    return (new CountCashRegisterAction)($till, $countedCents, User::query()->firstOrFail());
}

function cashIncome(): array
{
    return FinancialReport::for(FiscalYear::startingIn(2026))->incomeByCategory();
}

it('records a count that matches, without any movement', function (): void {
    barSale($this->till, 8_000);

    $count = countTill($this->till, 18_000);

    expect($count->expected)->toBe(18_000)
        ->and($count->counted)->toBe(18_000)
        ->and($count->difference())->toBe(0)
        ->and(CashRegisterEntry::count())->toBe(2);
});

it('files a surplus under other income', function (): void {
    barSale($this->till, 8_000);
    tillMovement($this->till, 7_000, Tournament::factory()->create());

    countTill($this->till, 26_000);

    $surplus = CashRegisterEntry::where('reason', 'cash_difference')->sole();

    expect($surplus->amount)->toBe(1_000)
        ->and($surplus->isClosed())->toBeTrue()
        ->and($this->till->currentBalance())->toBe(26_000)
        ->and(cashIncome())->toMatchArray(['bar_income' => 80.0, 'event_income' => 70.0, 'other_income' => 10.0]);
});

it('spreads a shortfall over the cash income since the last count, pro rata of the amounts', function (): void {
    barSale($this->till, 8_000);
    tillMovement($this->till, 7_000, Tournament::factory()->create());

    countTill($this->till, 24_500);

    // 5 € manquent : 80/150 au bar, 70/150 aux tournois — 2,67 € et 2,33 €.
    expect(CashRegisterEntry::where('reason', 'cash_difference')->orderBy('amount')->pluck('amount')->all())->toBe([-267, -233])
        ->and($this->till->currentBalance())->toBe(24_500)
        ->and(cashIncome())->toMatchArray(['bar_income' => 77.33, 'event_income' => 67.67]);
});

it('blames the shortfall on the money that came in after the previous count only', function (): void {
    barSale($this->till, 8_000);
    countTill($this->till, 18_000);

    $this->travel(1)->day();
    tillMovement($this->till, 7_000, Tournament::factory()->create());
    countTill($this->till, 24_500);

    expect(CashRegisterEntry::where('reason', 'cash_difference')->sole()->amount)->toBe(-500)
        ->and(cashIncome())->toMatchArray(['bar_income' => 80.0, 'event_income' => 65.0]);
});

it('files a shortfall under other expenses when no cash came in since the last count', function (): void {
    countTill($this->till, 9_500);

    $report = FinancialReport::for(FiscalYear::startingIn(2026));

    expect(CashRegisterEntry::where('reason', 'cash_difference')->sole()->amount)->toBe(-500)
        ->and($report->expensesByCategory())->toMatchArray(['other' => 5.0]);
});

it('lets the treasurer count the till from the screen, in euros and cents', function (): void {
    barSale($this->till, 250);

    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.cash-register')
        ->call('openCount')
        ->assertSee('102,50')
        ->set('countedAmount', '102,00')
        ->set('countNotes', 'Compté avec Jean')
        ->call('saveCount')
        ->assertHasNoErrors();

    $count = CashRegisterCount::sole();

    expect($count->counted)->toBe(10_200)
        ->and($count->difference())->toBe(-50)
        ->and($count->notes)->toBe('Compté avec Jean')
        ->and($count->counted_by_id)->toBe($this->treasurer->id);
});

it('shows when the till was last counted, and how it went', function (): void {
    countTill($this->till, 9_500);

    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.cash-register')
        ->assertSee(__('Last count'))
        ->assertSee('01/10/2026')
        ->assertSee('-5,00');
});

it('refuses the count to a reader', function (): void {
    $reader = User::factory()->isCommitteeMember()->create();

    Livewire::actingAs($reader)
        ->test('pages::club-admin.treasury.cash-register')
        ->set('countedAmount', '100')
        ->call('saveCount')
        ->assertForbidden();

    expect(CashRegisterCount::count())->toBe(0);
});
