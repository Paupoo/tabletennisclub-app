<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — l'écran des ventes
|--------------------------------------------------------------------------
|
| Le comité lit tout (décision du 2026-09-24), mais n'a pas `bar.access` : lui
| donner ouvrirait le comptoir. L'écran des ventes vit donc sous `/bar` sans en
| prendre le verrou, et n'exige que `bar.stats.view`.
|
*/

it('opens to the committee, which has no access to the counter', function (): void {
    $committee = User::factory()->isCommitteeMember()->create();

    $this->actingAs($committee)->get(route('bar.stats.index'))->assertOk();
    $this->actingAs($committee)->get(route('bar.index'))->assertForbidden();
});

it('stays closed to a barman', function (): void {
    $barman = User::factory()->withRole(Role::BARMAN)->create();

    $this->actingAs($barman)->get(route('bar.stats.index'))->assertForbidden();
});

it('turns each preset into the days it covers', function (string $preset, string $firstDay, string $lastDay): void {
    $this->travelTo(Carbon::parse('2026-09-26 12:00'));

    Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test('pages::bar.stats')
        ->set('period', $preset)
        ->assertSet('firstDay', $firstDay)
        ->assertSet('lastDay', $lastDay);
})->with([
    'this month' => ['this_month', '2026-09-01', '2026-09-26'],
    'last month' => ['last_month', '2026-08-01', '2026-08-31'],
    'last three months' => ['last_three_months', '2026-06-27', '2026-09-26'],
    'this year' => ['this_year', '2026-01-01', '2026-09-26'],
    // Sans saison active, une saison de club court de septembre à juin.
    'season, none declared' => ['season', '2026-09-01', '2026-09-26'],
]);

it('takes the season the club declared, up to today', function (): void {
    $this->travelTo(Carbon::parse('2026-03-10 12:00'));
    Season::factory()->create([
        'start_at' => Carbon::parse('2025-08-25'),
        'end_at' => Carbon::parse('2026-06-30'),
        'is_active' => true,
    ]);
    Cache::forget('season.current');

    Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test('pages::bar.stats')
        ->set('period', 'season')
        ->assertSet('firstDay', '2025-08-25')
        ->assertSet('lastDay', '2026-03-10');
});

it('opens on the current season', function (): void {
    $this->travelTo(Carbon::parse('2026-09-26 12:00'));

    Livewire::actingAs(User::factory()->isCommitteeMember()->create())
        ->test('pages::bar.stats')
        ->assertSet('period', 'season')
        ->assertSet('firstDay', '2026-09-01');
});

it('lists what sold on the days typed in, best sellers first and sleepers last', function (): void {
    $beers = BarCategory::create(['name' => 'Bières']);
    $jupiler = BarProduct::create(['name' => 'Jupiler', 'sale_price' => 200, 'is_available' => 1, 'category_id' => $beers->id]);
    BarProduct::create(['name' => 'Chimay bleue', 'sale_price' => 350, 'is_available' => 1, 'category_id' => $beers->id]);
    $order = new BarOrder(['total_price' => 0, 'is_paid' => 1, 'payment_method' => 'Cash']);
    $order->created_at = Carbon::parse('2026-05-15 21:00');
    $order->save();
    $order->items()->create(['product_id' => $jupiler->id, 'quantity' => 37, 'unit_price' => 200, 'total_price' => 7400]);

    $committee = User::factory()->isCommitteeMember()->create();

    Livewire::actingAs($committee)
        ->test('pages::bar.stats')
        ->set('firstDay', '2026-05-01')
        ->assertSet('period', 'custom');

    // Le premier rendu, et non la réponse d'un `set` : celle-ci est du JSON, où
    // « Bières » arrive échappé et ne se retrouve plus.
    Livewire::withQueryParams(['period' => 'custom', 'from' => '2026-05-01', 'to' => '2026-05-31'])
        ->actingAs($committee)
        ->test('pages::bar.stats')
        ->assertSeeInOrder(['Bières', 'Jupiler', '37', '8,4', 'Chimay bleue', __('Not sold')]);
});
