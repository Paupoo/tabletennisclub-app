<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarRestockingAdjustment;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Notifications\BarRestockingDigestNotification;
use App\Domains\Bar\Services\RestockingDigest;
use App\Domains\Bar\Services\RestockingList;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendBarRestockingDigestJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Bar — le digest « stock bas » du samedi
|--------------------------------------------------------------------------
|
| Le samedi à 10 h : le lendemain des matchs, les magasins sont ouverts, et qui
| n'y va pas le samedi a la semaine devant lui. Aux porteurs de la permission de
| faire les courses, et seulement quand quelque chose doit être acheté — pas de
| « tout va bien ».
|
*/

beforeEach(function (): void {
    $this->storeKeeper = User::factory()->withRole(Role::STORE_KEEPER)->create(['first_name' => 'Xavier']);
    $beers = BarCategory::create(['name' => 'Bières']);
    $this->jupiler = restockingDigestProduct('Jupiler', $beers, stock: 5, min: 12, max: 48, packSize: 24, packLabel: 'casier');
    $this->coca = restockingDigestProduct('Coca-Cola', $beers, stock: 14, min: 6, max: 30, packSize: 6, packLabel: 'pack');
});

function restockingDigestProduct(string $name, BarCategory $category, int $stock, int $min, int $max, int $packSize, string $packLabel): BarProduct
{
    $product = BarProduct::create([
        'name' => $name, 'sale_price' => 200, 'is_available' => 1, 'category_id' => $category->id,
        'low_stock_threshold' => $min, 'max_stock' => $max, 'pack_size' => $packSize, 'pack_label' => $packLabel,
    ]);
    BarStockMovement::create(['product_id' => $product->id, 'quantity' => $stock, 'remaining_quantity' => $stock, 'movement_type' => BarStockMovement::TYPE_IN]);

    return $product;
}

it('sends the list to whoever may go shopping, and to them only', function (): void {
    Queue::fake();
    User::factory()->withRole(Role::BARMAN)->create();

    $this->artisan('bar:restocking-digest')->assertSuccessful();

    Queue::assertPushed(SendBarRestockingDigestJob::class, 1);
    Queue::assertPushed(SendBarRestockingDigestJob::class, fn (SendBarRestockingDigestJob $job): bool => $job->userId === $this->storeKeeper->id);
});

it('sends nothing when nothing must be bought', function (): void {
    Queue::fake();
    $this->jupiler->update(['max_stock' => null]);

    $this->artisan('bar:restocking-digest')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('is scheduled on Saturday at 10:00', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'bar:restocking-digest'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 10 * * 6');
});

it('lists what must be bought, not what fits if there is room, and links to the list', function (): void {
    $html = (string) new BarRestockingDigestNotification(app(RestockingList::class)->current()['to_buy'], null)
        ->toMail($this->storeKeeper)
        ->render();

    expect($html)->toContain('Jupiler')
        ->toContain('2 × casier')
        ->not->toContain('Coca-Cola')
        ->toContain(route('bar.restocking.index'));
});

it('asks to plan the shopping, in French', function (): void {
    app()->setLocale('fr_BE');

    $html = (string) new BarRestockingDigestNotification(app(RestockingList::class)->current()['to_buy'], null)
        ->toMail($this->storeKeeper)
        ->render();

    expect($html)->toContain('Ces produits ont atteint leur seuil minimum, veuillez planifier les courses.');
});

it('says so when someone is already shopping', function (): void {
    $trip = BarRestocking::query()->create([
        'status' => BarRestocking::STATUS_IN_PROGRESS,
        'shopper_id' => User::factory()->create(['first_name' => 'Aurélien', 'last_name' => 'Paulus'])->id,
        'started_at' => now(),
    ]);

    $html = (string) new BarRestockingDigestNotification(app(RestockingList::class)->current()['to_buy'], $trip)
        ->toMail($this->storeKeeper)
        ->render();

    expect($html)->toContain(e(__(':name is already doing the shopping, since :date.', [
        'name' => 'Aurélien Paulus',
        'date' => $trip->started_at->translatedFormat('l j F'),
    ])));
});

it('sends nothing if the bar was filled between the command and the mail', function (): void {
    Notification::fake();
    $this->jupiler->update(['max_stock' => null]);

    new SendBarRestockingDigestJob($this->storeKeeper->id)->handle(app(RestockingList::class), app(RestockingDigest::class));

    Notification::assertNothingSent();
});

it('mails the list when the job runs', function (): void {
    Notification::fake();

    new SendBarRestockingDigestJob($this->storeKeeper->id)->handle(app(RestockingList::class), app(RestockingDigest::class));

    Notification::assertSentTo($this->storeKeeper, BarRestockingDigestNotification::class);
});

it('goes out when nothing must be bought but the automatic restocking moved something this week', function (): void {
    Queue::fake();
    $this->jupiler->update(['max_stock' => null]);
    BarRestockingAdjustment::query()->create(['product_id' => $this->coca->id, 'old_min' => 6, 'new_min' => 8, 'old_max' => 24, 'new_max' => 30]);

    $this->artisan('bar:restocking-digest')->assertSuccessful();

    Queue::assertPushed(SendBarRestockingDigestJob::class, 1);
});

it('does not go out for an adjustment older than the week', function (): void {
    Queue::fake();
    $this->jupiler->update(['max_stock' => null]);
    $this->travel(-8)->days();
    BarRestockingAdjustment::query()->create(['product_id' => $this->coca->id, 'old_min' => 6, 'new_min' => 8, 'old_max' => 24, 'new_max' => 30]);
    $this->travelBack();

    $this->artisan('bar:restocking-digest')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('reports the week\'s adjustments and the products that no longer sell', function (): void {
    // Le Coca se vend, le Fanta dort alors qu'il est au réassort.
    $order = BarOrder::create(['total_price' => 0, 'is_paid' => 1, 'payment_method' => 'cash']);
    $order->items()->create(['product_id' => $this->coca->id, 'quantity' => 4, 'unit_price' => 200, 'total_price' => 800]);
    restockingDigestProduct('Fanta', $this->jupiler->category, stock: 18, min: 6, max: 18, packSize: 6, packLabel: 'pack');
    BarRestockingAdjustment::query()->create(['product_id' => $this->coca->id, 'old_min' => 6, 'new_min' => 8, 'old_max' => 24, 'new_max' => 30]);

    Notification::fake();
    new SendBarRestockingDigestJob($this->storeKeeper->id)->handle(app(RestockingList::class), app(RestockingDigest::class));

    Notification::assertSentTo($this->storeKeeper, BarRestockingDigestNotification::class, function (BarRestockingDigestNotification $notification): bool {
        $html = (string) $notification->toMail($this->storeKeeper)->render();

        return str_contains($html, __('Settings adjusted this week'))
            && str_contains($html, 'Coca-Cola')
            && str_contains($html, '24 → 30')
            && str_contains($html, e(__('No longer selling')))
            && str_contains($html, 'Fanta');
    });
});
