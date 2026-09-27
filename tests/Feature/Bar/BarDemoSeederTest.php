<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Services\BarSalesReport;
use App\Domains\Bar\Services\RestockingList;
use App\Domains\Bar\Services\RestockingSuggestions;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Role;
use Database\Seeders\BarDemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Bar — la démo qui fait vivre le réassort et les stats
|--------------------------------------------------------------------------
|
| Un seeder qui a l'air juste peut ne rien montrer : une vente qui déborde vide
| « À acheter », un stock passe sous zéro au FIFO. Ce test tient ce qui a été
| décidé le 2026-09-26, et que deux lancements donnent la même base.
|
*/

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo(Carbon::parse('2026-09-26 12:00'));
    $this->firstUser = User::factory()->create();
    $this->xavier = User::factory()->create(['email' => 'xavier.coenen@test.com', 'first_name' => 'Xavier']);

    $this->seed(BarDemoSeeder::class);
});

function barDemoProduct(string $name): BarProduct
{
    return BarProduct::query()->withStock()->where('name', $name)->sole();
}

function barDemoLastThreeMonths(): array
{
    $rows = collect(app(BarSalesReport::class)->between(today()->subMonthsNoOverflow(3)->addDay(), today()))
        ->flatMap(fn (array $group): array => $group['products']);

    return $rows->keyBy('name')->all();
}

it('never lets a stock fall below zero', function (): void {
    $negative = DB::table('bar_stock_movements')
        ->selectRaw("product_id, SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE -quantity END) as balance")
        ->groupBy('product_id')
        ->havingRaw("SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE -quantity END) < 0")
        ->count();

    expect($negative)->toBe(0)
        ->and(DB::table('bar_stock_movements')->where('movement_type', 'IN')->where('remaining_quantity', '<', 0)->count())->toBe(0);
});

it('leaves four products to buy and four that fit if there is room', function (): void {
    $list = app(RestockingList::class)->current();

    expect(array_column($list['to_buy'], 'name'))->toEqualCanonicalizing(['Jupiler', 'Coca-Cola', 'Café', 'Chips sel'])
        ->and(array_column($list['if_room'], 'name'))->toEqualCanonicalizing(['Jupiler 0.0', 'Coca Zero', 'Eau plate', 'Thé']);
});

it('leaves three products sold but never set, with a suggestion to apply', function (): void {
    $unset = BarProduct::query()->whereNull('max_stock')->pluck('id', 'name');
    $suggestions = app(RestockingSuggestions::class)->all();

    expect($unset->keys()->all())->toEqualCanonicalizing(['Duvel', 'Chimay bleue', "Balle d'entraînement"])
        ->and(array_intersect_key($suggestions, array_flip($unset->all())))->toHaveCount(3);
});

it('shows a product that never sold, one that rises and one that falls', function (): void {
    $rows = barDemoLastThreeMonths();

    expect($rows['Eau pétillante']['units'])->toBe(0)
        ->and($rows['Coca Zero']['change'])->toBeGreaterThan(0)
        ->and($rows['Leffe blonde']['change'])->toBeLessThan(0);
});

it('mixes in offered drinks and leaves a couple of tabs open', function (): void {
    expect(BarOrder::query()->where('payment_method', 'offered')->count())->toBeGreaterThan(0)
        ->and(BarOrder::query()->where('is_paid', 0)->count())->toBe(2);
});

it('closes three past trips, the one Xavier paid with a submitted expense report', function (): void {
    $trips = BarRestocking::query()->with('lines')->get();

    expect($trips)->toHaveCount(3)
        ->and($trips->every(fn (BarRestocking $trip): bool => $trip->status === BarRestocking::STATUS_CLOSED))->toBeTrue()
        ->and($trips->pluck('paid_by')->all())->toEqualCanonicalizing(['club', 'nobody', 'me'])
        ->and(BarRestocking::inProgress())->toBeNull();

    $paid = $trips->firstWhere('paid_by', 'me');
    $report = ExpenseReport::query()->with('files')->findOrFail($paid->expense_report_id);

    expect($paid->shopper_id)->toBe($this->xavier->id)
        ->and($report->user_id)->toBe($this->xavier->id)
        ->and($report->category)->toBe(ExpenseCategory::Bar)
        ->and($report->status)->toBe(ExpenseReportStatus::Submitted)
        ->and($report->files)->toHaveCount(1);
    expect(DB::table('bar_stock_movements')->where('restocking_id', $paid->id)->count())->toBeGreaterThan(0);
});

it('puts Coca Zero alone in automatic mode, and lets the sandwich cover a single week', function (): void {
    expect(BarProduct::query()->where('restocking_mode', 'auto')->pluck('name')->all())->toBe(['Coca Zero'])
        ->and(barDemoProduct('Sandwich')->restocking_weeks)->toBe(1);
});

it('makes Xavier and the first account store keepers', function (): void {
    expect($this->xavier->fresh()->hasRole(Role::STORE_KEEPER->value))->toBeTrue()
        ->and($this->firstUser->fresh()->hasRole(Role::STORE_KEEPER->value))->toBeTrue();
});

it('gives the same bar when run twice', function (): void {
    $snapshot = fn (): array => [
        BarProduct::query()->count(),
        BarOrder::query()->count(),
        DB::table('bar_order_items')->sum('quantity'),
        BarRestocking::query()->count(),
        ExpenseReport::query()->count(),
        BarProduct::query()->withStock()->get()->mapWithKeys(fn (BarProduct $p): array => [$p->name => $p->stock])->all(),
    ];

    $first = $snapshot();
    $this->seed(BarDemoSeeder::class);

    expect($snapshot())->toBe($first);
});
