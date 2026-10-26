<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Bar — le cash du bar entre dans la caisse du club
|--------------------------------------------------------------------------
|
| Une commande réglée en espèces ne laissait aucune trace en trésorerie : ni
| mouvement de caisse, ni paiement. La caisse ignorait l'argent qu'elle
| contenait, et la rubrique « Bar » du rapport financier restait vide.
|
| Ce qui est verrouillé ici :
|
| - le cash écrit un mouvement par commande dans la caisse du club, lié à la
|   commande : le rapport financier le range alors en « Bar » de lui-même ;
| - l'offert et le QR n'y écrivent rien — aucune espèce n'entre au tiroir ;
| - un double règlement ne double pas le mouvement ;
| - la reprise rattrape l'historique à sa date réelle, sans rien écrire deux fois.
|
*/

function cashBarOrder(User $barman, int $cents = 250, array $attributes = []): BarOrder
{
    return BarOrder::create([
        'created_by' => $barman->id,
        'total_price' => $cents,
        'is_paid' => 0,
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-10-24 14:00:00'));

    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();
    $this->barman = User::factory()->isAdmin()->create();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('puts the cash of an order in the club till, tied to the order', function (): void {
    $till = CashRegister::create(['name' => 'Caisse principale']);
    $order = cashBarOrder($this->barman, 750);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', $order), ['method' => 'cash'])
        ->assertRedirect(route('bar.orders.index'));

    $entry = CashRegisterEntry::sole();

    expect($entry->cash_register_id)->toBe($till->id)
        ->and($entry->amount)->toBe(750)
        ->and($entry->reason)->toBe('bar_sale')
        ->and($entry->payable_type)->toBe(BarOrder::class)
        ->and($entry->payable_id)->toBe($order->id)
        ->and($entry->recorded_by_id)->toBe($this->barman->id);
});

it('files the cash of the bar under bar income', function (): void {
    CashRegister::create(['name' => 'Caisse principale']);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', cashBarOrder($this->barman, 750)), ['method' => 'cash']);

    expect(FinancialReport::for(FiscalYear::startingIn(2026))->incomeByCategory())->toBe(['bar_income' => 7.5]);
});

it('opens the club till when none exists yet', function (): void {
    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', cashBarOrder($this->barman)), ['method' => 'cash']);

    expect(CashRegister::count())->toBe(1)
        ->and(CashRegisterEntry::sole()->amount)->toBe(250);
});

it('puts nothing in the till for an order paid by QR', function (): void {
    CashRegister::create(['name' => 'Caisse principale']);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', cashBarOrder($this->barman)), ['method' => 'qr']);

    expect(CashRegisterEntry::count())->toBe(0);
});

it('puts nothing in the till for an offered order', function (): void {
    CashRegister::create(['name' => 'Caisse principale']);

    $this->actingAs($this->barman)
        ->post(route('bar.payment.pay', cashBarOrder($this->barman)), ['method' => 'offered', 'manual_reason' => 'Anniversaire']);

    expect(CashRegisterEntry::count())->toBe(0);
});

it('does not count the cash twice when the order is settled twice', function (): void {
    CashRegister::create(['name' => 'Caisse principale']);
    $order = cashBarOrder($this->barman);

    $this->actingAs($this->barman)->post(route('bar.payment.pay', $order), ['method' => 'cash']);
    $this->actingAs($this->barman)->post(route('bar.payment.pay', $order), ['method' => 'cash'])
        ->assertSessionHas('error', 'Commande déjà payée.');

    expect(CashRegisterEntry::count())->toBe(1);
});

describe('the backfill', function (): void {
    beforeEach(function (): void {
        $this->till = CashRegister::create(['name' => 'Caisse principale']);
    });

    it('brings in the cash orders paid since the given day, dated when the bar took the money', function (): void {
        $september = cashBarOrder($this->barman, 400, ['is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-09-12 18:06:09']);
        cashBarOrder($this->barman, 650, ['is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-06-16 15:21:47']);
        cashBarOrder($this->barman, 300, ['is_paid' => 1, 'payment_method' => 'qr', 'paid_at' => '2026-09-20 18:00:00']);
        cashBarOrder($this->barman, 300, ['is_paid' => 1, 'payment_method' => 'offered', 'paid_at' => '2026-09-20 18:00:00']);
        cashBarOrder($this->barman, 300);

        $this->artisan('bar:backfill-cash', ['since' => '2026-09-01'])->assertSuccessful();

        $entry = CashRegisterEntry::sole();

        expect($entry->payable_id)->toBe($september->id)
            ->and($entry->amount)->toBe(400)
            ->and($entry->cash_register_id)->toBe($this->till->id)
            ->and($entry->recorded_by_id)->toBe($this->barman->id)
            ->and($entry->created_at->toDateTimeString())->toBe('2026-09-12 18:06:09');
    });

    it('writes nothing twice when run again', function (): void {
        cashBarOrder($this->barman, 400, ['is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-09-12 18:06:09']);

        $this->artisan('bar:backfill-cash', ['since' => '2026-09-01'])->assertSuccessful();
        $this->artisan('bar:backfill-cash', ['since' => '2026-09-01'])->assertSuccessful();

        expect(CashRegisterEntry::count())->toBe(1);
    });

    it('puts an order whose barman is gone in the name of the till holder', function (): void {
        $holder = User::factory()->create();
        $this->till->update(['held_by_user_id' => $holder->id]);
        cashBarOrder($this->barman, 400, ['created_by' => null, 'is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-09-12 18:06:09']);

        $this->artisan('bar:backfill-cash', ['since' => '2026-09-01'])->assertSuccessful();

        expect(CashRegisterEntry::sole()->recorded_by_id)->toBe($holder->id);
    });

    it('puts it in the name of whoever is given, when asked', function (): void {
        $treasurer = User::factory()->create();
        cashBarOrder($this->barman, 400, ['created_by' => null, 'is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-09-12 18:06:09']);

        $this->artisan('bar:backfill-cash', ['since' => '2026-09-01', '--recorded-by' => $treasurer->id])->assertSuccessful();

        expect(CashRegisterEntry::sole()->recorded_by_id)->toBe($treasurer->id);
    });

    it('writes nothing when an order has nobody to be put in the name of', function (): void {
        cashBarOrder($this->barman, 400, ['is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-09-12 18:06:09']);
        cashBarOrder($this->barman, 250, ['created_by' => null, 'is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-09-13 18:06:09']);

        $this->artisan('bar:backfill-cash', ['since' => '2026-09-01'])
            ->expectsOutputToContain('1 cash orders have no barman any more')
            ->assertFailed();

        expect(CashRegisterEntry::count())->toBe(0);
    });

    it('only counts on a dry run', function (): void {
        cashBarOrder($this->barman, 400, ['is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-09-12 18:06:09']);
        cashBarOrder($this->barman, 250, ['is_paid' => 1, 'payment_method' => 'cash', 'paid_at' => '2026-10-02 20:00:00']);

        $this->artisan('bar:backfill-cash', ['since' => '2026-09-01', '--dry-run' => true])
            ->expectsOutputToContain('2 cash orders (6.50 €) would be brought into the till.')
            ->assertSuccessful();

        expect(CashRegisterEntry::count())->toBe(0);
    });
});
