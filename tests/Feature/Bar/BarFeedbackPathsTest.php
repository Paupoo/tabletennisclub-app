<?php

declare(strict_types=1);

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarStockMovement;
use App\Domains\Bar\Services\CashSheetService;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Models\AppSetting;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Bar — les chemins où quelque chose se passe mal
|--------------------------------------------------------------------------
|
| Les écrans du bar rendaient tous correctement ; ce qui ne marchait pas, c'est
| ce qui arrive quand l'action échoue. Trois défauts, tous invisibles :
|
| - une validation de commande en échec partait sur un RouteNotFoundException,
|   parce que le contrôleur redirigeait vers `bar.carts.show` — un nom de route
|   qui n'existe pas. L'erreur métier devenait une page 500, et le panier était
|   perdu ;
| - l'échec d'envoi de la feuille de caisse était flashé en `warning`, un niveau
|   que le pont flash→toast du layout ne relayait pas. Le barman rangeait la
|   caisse en croyant le trésorier servi ;
| - deux routes pointaient vers du vide, dont une vers une méthode inexistante.
|
| Le pont flash→toast lui-même est couvert par tests/Feature/Shared/FlashToastBridgeTest.php :
| il sert toute l'application, ce n'est pas au bar de le garder.
|
| Ces tests verrouillent les trois. Ils visent les chemins d'échec en priorité :
| ce sont ceux qu'aucun clic de recette n'emprunte.
|
*/

/** Ouvre une ardoise, y sert une bière, et l'enregistre — le vrai parcours. */
function openTabAndServe(User $user, string $name, int $productId): void
{
    Livewire::actingAs($user)->test('pages::bar.counter')
        ->set('tabNameInput', $name)
        ->call('openTab')
        ->call('add', $productId);

    Livewire::actingAs($user)->test('pages::bar.cart')->call('validateOrder', 'validate');
}

beforeEach(function (): void {
    // La journée de caisse commence à 6 h (CashSheetService) : entre minuit et
    // 6 h, une commande créée « maintenant » compte pour la veille et les tests
    // qui lisent la journée du jour échouaient. On se place en pleine journée.
    $this->travelTo(now()->setTime(14, 0));

    $this->manager = User::factory()->isAdmin()->create();

    $this->category = BarCategory::create(['name' => 'Bières']);

    $this->product = BarProduct::create([
        'name' => 'Jupiler 25 cl',
        'sale_price' => 180,
        'is_available' => 1,
        'category_id' => $this->category->id,
    ]);

    // `remaining_quantity` est ce que StockService::consumeFIFO consomme : un lot
    // d'entrée sans lui est invisible au FIFO, et toute validation de commande
    // échoue sur « Stock insuffisant pour appliquer la sortie FIFO » — même avec
    // du stock au compteur. Les fixtures du bar l'omettaient, si bien qu'aucun
    // test n'avait jamais mené une commande jusqu'au bout.
    BarStockMovement::create([
        'product_id' => $this->product->id,
        'quantity' => 2,
        'remaining_quantity' => 2,
        'movement_type' => 'IN',
        'created_by' => $this->manager->id,
    ]);
});

it('says why a checkout failed instead of losing the order', function (): void {
    // Trois au ticket pour deux en stock : validateProductStock lève une
    // RuntimeException. Ce chemin partait autrefois sur un RouteNotFoundException,
    // donc sur une page 500, et le panier — trente taps de travail — n'avait plus
    // de chemin visible pour revenir.
    session()->put(['cart' => [$this->product->id => 3], 'bar_tab_name' => 'Alpa A']);

    Livewire::actingAs($this->manager)
        ->test('pages::bar.cart')
        ->call('validateOrder', 'validate')
        ->assertNoRedirect();

    expect(session('cart'))->toBe([$this->product->id => 3])
        ->and(BarOrder::query()->count())->toBe(0);
});

it('reaches the cart screen under the name the controller redirects to', function (): void {
    // Le défaut n'était pas une faute de frappe visible : `bar.carts.show` et
    // `bar.cart.show` se lisent pareil. Le test nomme la route attendue.
    expect(Route::has('bar.cart.show'))->toBeTrue()
        ->and(Route::has('bar.carts.show'))->toBeFalse();
});

it('says so out loud when the cash sheet cannot be sent', function (): void {
    AppSetting::set('treasurer_email', 'previous@example.test');
    $this->partialMock(
        CashSheetService::class,
        fn ($mock) => $mock->shouldReceive('sendCsv')->once()->andReturn(false)
    );

    // Une commande du jour, sinon le garde `orders_total === 0` sort avant l'envoi
    // et on ne mesurerait pas le chemin d'échec du mailer.
    openTabAndServe($this->manager, 'Alpa A', $this->product->id);

    $this->actingAs($this->manager)
        ->post(route('bar.cashSheet.send'), [
            'date' => now()->toDateString(),
            'to' => 'tresorier@example.test',
            'save_default' => true,
        ])
        ->assertSessionHas('error');

    expect(AppSetting::get('treasurer_email'))->toBe('previous@example.test');
});

it('keeps after-midnight sales in the same cash sheet business day', function (): void {
    $overnight = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => true,
    ]);
    $overnight->forceFill(['created_at' => '2026-09-20 02:00:00'])->saveQuietly();

    $nextBusinessDay = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 300,
        'is_paid' => true,
    ]);
    $nextBusinessDay->forceFill(['created_at' => '2026-09-20 07:00:00'])->saveQuietly();

    $summary = app(CashSheetService::class)->build('2026-09-19')[0];

    expect($summary['orders_total'])->toBe(1)
        ->and($summary['sold_total_cents'])->toBe(180);
});

it('attributes receipts to the business day the order was paid', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => true,
        'payment_method' => 'cash',
    ]);
    $order->forceFill([
        'created_at' => now()->subDay()->setTime(14, 0),
        'paid_at' => now()->setTime(14, 0),
    ])->saveQuietly();

    $previousDaySummary = app(CashSheetService::class)->build(now()->subDay()->toDateString())[0];
    $paymentDaySummary = app(CashSheetService::class)->build(now()->toDateString())[0];

    expect($previousDaySummary['sold_total_cents'])->toBe(180)
        ->and($previousDaySummary['received_total_cents'])->toBe(0)
        ->and($paymentDaySummary['orders_total'])->toBe(0)
        ->and($paymentDaySummary['payments_total'])->toBe(1)
        ->and($paymentDaySummary['received_total_cents'])->toBe(180)
        ->and($paymentDaySummary['by_method_cents']['cash'])->toBe(180);
});

it('can email a cash sheet containing payments for orders created on an earlier day', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => true,
        'payment_method' => 'cash',
    ]);
    $order->forceFill([
        'created_at' => now()->subDay()->setTime(14, 0),
        'paid_at' => now()->setTime(14, 0),
    ])->saveQuietly();

    $this->partialMock(
        CashSheetService::class,
        fn ($mock) => $mock->shouldReceive('sendCsv')->once()->andReturn(true)
    );

    $this->actingAs($this->manager)
        ->post(route('bar.cashSheet.send'), [
            'date' => now()->toDateString(),
            'to' => 'treasurer@example.test',
            'save_default' => true,
        ])
        ->assertRedirect(route('bar.cashSheet.index', ['date' => now()->toDateString()]))
        ->assertSessionHas('success');

    expect(AppSetting::get('treasurer_email'))->toBe('treasurer@example.test');
});

it('lists active business days and navigates to payments made after the sale day', function (): void {
    $previousBusinessDate = now()->subDay()->toDateString();
    $olderOrder = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 500,
        'is_paid' => true,
        'payment_method' => 'cash',
    ]);
    $olderOrder->forceFill([
        'created_at' => "{$previousBusinessDate} 14:00:00",
        'paid_at' => now()->setTime(14, 0),
    ])->saveQuietly();

    BarOrderItem::create([
        'order_id' => $olderOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 250,
        'total_price' => 500,
    ]);

    $todayOrder = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 200,
        'is_paid' => true,
        'payment_method' => 'qr',
    ]);

    $activity = app(CashSheetService::class)->activity(now()->toDateString());

    expect($activity['days'][0])
        ->toMatchArray([
            'date' => now()->toDateString(),
            'orders_total' => 1,
            'payments_total' => 2,
            'received_total_cents' => 700,
        ])
        ->and($activity['days'][1])
        ->toMatchArray([
            'date' => $previousBusinessDate,
            'orders_total' => 1,
            'items_total' => 2,
            'payments_total' => 0,
        ])
        ->and($activity['previous'])->toBe($previousBusinessDate)
        ->and($activity['next'])->toBeNull();

    $this->actingAs($this->manager)
        ->get(route('bar.cashSheet.index', ['date' => now()->toDateString()]))
        ->assertOk()
        ->assertSee("Journées avec de l'activité", escape: false)
        ->assertSee('Ventes')
        ->assertSee('Paiements')
        ->assertSee(route('bar.cashSheet.index', ['date' => $previousBusinessDate]), escape: false);
});

it('excludes offered orders and counts unknown paid methods as other received', function (): void {
    BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => true,
        'payment_method' => 'cash',
    ]);
    BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 300,
        'is_paid' => true,
        'payment_method' => 'offered',
    ]);
    BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 250,
        'is_paid' => true,
    ]);

    $service = app(CashSheetService::class);
    $summary = $service->build(now()->toDateString())[0];
    $activeDay = collect($service->activity(now()->toDateString())['days'])
        ->firstWhere('date', now()->toDateString());

    expect($summary['sold_total_cents'])->toBe(730)
        ->and($summary['received_total_cents'])->toBe(430)
        ->and($summary['by_method_cents']['cash'])->toBe(180)
        ->and($summary['by_method_cents']['other'])->toBe(250)
        ->and($summary['by_method_cents']['offered'])->toBe(300)
        ->and($summary['other_payments_cents'])->toBe(250)
        ->and($activeDay['received_total_cents'])->toBe(430);
});

it('stores and retrieves the default treasurer email', function (): void {
    $service = app(CashSheetService::class);

    $service->saveDefaultEmail('treasurer@example.test');

    expect(AppSetting::get('treasurer_email'))->toBe('treasurer@example.test')
        ->and($service->getDefaultEmail())->toBe('treasurer@example.test');
});

it('neutralizes spreadsheet formula product names in the CSV export', function (): void {
    $order = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 720,
        'is_paid' => true,
        'payment_method' => 'cash',
    ]);

    foreach (['=HYPERLINK("https://example.test")', ' +SUM(1,1)', '-1+1', '@SUM(1,1)'] as $name) {
        $product = BarProduct::create([
            'name' => $name,
            'sale_price' => 180,
            'is_available' => 1,
            'category_id' => $this->category->id,
        ]);

        BarOrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 180,
            'total_price' => 180,
        ]);
    }

    $csv = app(CashSheetService::class)->build(now()->toDateString())[2];

    expect($csv)
        ->toContain("'=HYPERLINK")
        ->toContain("' +SUM")
        ->toContain("'-1+1")
        ->toContain("'@SUM");
});

it('exports the historical prices, payment methods and transaction details in currency units', function (): void {
    $this->product->update([
        'name' => "Coffee; \"du jour\"\nRéserve",
        'sale_price' => 170,
    ]);

    $cashOrder = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 300,
        'is_paid' => true,
        'paid_at' => now(),
        'payment_method' => 'cash',
    ]);

    BarOrderItem::create([
        'order_id' => $cashOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 150,
        'total_price' => 300,
    ]);

    $qrOrder = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 250,
        'is_paid' => true,
        'paid_at' => now(),
        'payment_method' => 'qr',
    ]);

    BarOrderItem::create([
        'order_id' => $qrOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 250,
        'total_price' => 250,
    ]);

    $offeredOrder = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 100,
        'is_paid' => true,
        'paid_at' => now(),
        'payment_method' => 'offered',
    ]);

    BarOrderItem::create([
        'order_id' => $offeredOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'total_price' => 100,
    ]);

    $laterPaidOrder = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 200,
        'is_paid' => true,
        'paid_at' => now(),
        'payment_method' => 'cash',
    ]);
    $laterPaidOrder->forceFill([
        'created_at' => now()->subDay()->setTime(14, 0),
    ])->saveQuietly();

    BarOrderItem::create([
        'order_id' => $laterPaidOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 200,
        'total_price' => 200,
    ]);

    $csv = app(CashSheetService::class)->build(now()->toDateString())[2];
    $stream = fopen('php://temp', 'r+');
    fwrite($stream, $csv);
    rewind($stream);
    $csvRows = [];

    while (($row = fgetcsv($stream, separator: ';', enclosure: '"', escape: '')) !== false) {
        $csvRows[] = $row;
    }

    fclose($stream);

    $productRow = collect($csvRows)->first(
        fn (array $row): bool => ($row[0] ?? null) === "Coffee; \"du jour\"\nRéserve"
            && ($row[2] ?? null) === '1.50'
    );
    $laterPaidTransaction = collect($csvRows)->first(
        fn (array $row): bool => ($row[2] ?? null) === "Commande #{$laterPaidOrder->id}"
    );
    $reportValue = fn (string $label): ?string => collect($csvRows)
        ->first(fn (array $row): bool => ($row[0] ?? null) === $label)[1] ?? null;
    $paymentRow = fn (string $method): ?array => collect($csvRows)
        ->first(fn (array $row): bool => ($row[0] ?? null) === $method && count($row) === 3);

    expect($csv)
        ->toContain('RÉSUMÉ FINANCIER')
        ->and($reportValue('Date d’activité'))->toBe(now()->toDateString())
        ->and($reportValue('Ventes totales (date de commande)'))->toBe('6.50')
        ->and($reportValue('Encaissé en espèces (date d’encaissement)'))->toBe('5.00')
        ->and($reportValue('Encaissé par QR (date d’encaissement)'))->toBe('2.50')
        ->and($reportValue('Valeur des commandes offertes (non encaissée)'))->toBe('1.00')
        ->and($productRow)->not->toBeNull()
        ->and($productRow[1])->toBe('2')
        ->and($productRow[2])->toBe('1.50')
        ->and($productRow[3])->toBe('3.00')
        ->and($laterPaidTransaction[1])->toBe('Encaissement')
        ->and($laterPaidTransaction[0])->toBe(now()->format('Y-m-d H:i:s'))
        ->and($laterPaidTransaction[6])->toBe('cash')
        ->and($csv)->toContain("Commande #{$cashOrder->id}")
        ->and($csv)->toContain($this->manager->full_name)
        ->and($paymentRow('cash'))->toBe(['cash', '2', '5.00'])
        ->and($paymentRow('qr'))->toBe(['qr', '1', '2.50'])
        ->and($paymentRow('offered'))->toBe(['offered', '1', '1.00']);
});

it('uses the same generated report for the direct download and email attachment', function (): void {
    $date = now()->toDateString();
    $order = BarOrder::create([
        'created_by' => $this->manager->id,
        'total_price' => 180,
        'is_paid' => false,
    ]);
    BarOrderItem::create([
        'order_id' => $order->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 180,
        'total_price' => 180,
    ]);

    $expectedCsv = app(CashSheetService::class)->build($date)[2];

    $this->actingAs($this->manager)
        ->get(route('bar.cashSheet.download', ['date' => $date]))
        ->assertOk()
        ->assertContent("\xEF\xBB\xBF" . $expectedCsv);

    $emailCsv = null;
    $this->partialMock(
        CashSheetService::class,
        function ($mock) use (&$emailCsv): void {
            $mock->shouldReceive('sendCsv')
                ->once()
                ->withArgs(function (string $to, string $subject, string $body, string $date, string $csv) use (&$emailCsv): bool {
                    $emailCsv = $csv;

                    return true;
                })
                ->andReturn(true);
        }
    );

    $this->actingAs($this->manager)
        ->post(route('bar.cashSheet.send'), [
            'date' => $date,
            'to' => 'treasurer@example.test',
        ])
        ->assertSessionHas('success');

    expect($emailCsv)->toBe($expectedCsv);
});

it('says so out loud when there is nothing to send', function (): void {
    // Le garde portait sur `empty($csv)`, et buildCsv() écrit toujours sa ligne
    // d'en-tête : il ne se déclenchait donc jamais. Une journée sans vente partait
    // au trésorier sous la forme d'un CSV à zéro ligne, annoncée « Email envoyé
    // avec succès ». Le garde compte maintenant commandes et paiements.
    AppSetting::set('treasurer_email', 'previous@example.test');

    $this->actingAs($this->manager)
        ->post(route('bar.cashSheet.send'), [
            'date' => now()->subMonth()->toDateString(),
            'to' => 'tresorier@example.test',
            'save_default' => true,
        ])
        ->assertSessionHas('error');

    expect(AppSetting::get('treasurer_email'))->toBe('previous@example.test');
});

it('no longer registers a route towards a method that does not exist', function (): void {
    // POST bar/cart/pay visait BarCartController@pay, qui n'a jamais existé :
    // 500 garanti si quelqu'un l'atteignait. bar.payment.show.post était un
    // vestige d'avant le passage du QR en GET. Les routes d'écriture du panier ont
    // suivi quand le ticket est devenu un composant.
    expect(Route::has('bar.cart.pay'))->toBeFalse()
        ->and(Route::has('bar.payment.show.post'))->toBeFalse()
        ->and(Route::has('bar.cart.add'))->toBeFalse()
        ->and(Route::has('bar.cart.validate'))->toBeFalse()
        // Celle-ci reste : c'est l'écran du ticket, et le nom vers lequel un échec
        // de validation renvoyait avec une faute de frappe.
        ->and(Route::has('bar.cart.show'))->toBeTrue();
});

it('exposes the cash sheet send endpoint under a path that does not repeat itself', function (): void {
    // Le chemin était bar/cashsheet/bar/cashSheet/send : le préfixe du groupe,
    // puis le même préfixe écrit à la main dans la route.
    expect(route('bar.cashSheet.send', absolute: false))->toBe('/bar/cashsheet/send');
});

it('offers the cash-out action on every order of the queue', function (): void {
    // La file ne contient que des commandes impayées (BarOrderController::index
    // filtre is_paid = 0), donc la branche « Payé » des cartes ne pouvait pas
    // s'afficher. Elle affirmait pourtant au lecteur que la file les mélange.
    openTabAndServe($this->manager, 'Alpa A', $this->product->id);

    $this->actingAs($this->manager)
        ->get(route('bar.orders.index'))
        ->assertOk()
        ->assertSee('Non payé')
        ->assertDontSee('>Payé<', escape: false);
});
