<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Bar\Models\BarCategory;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestocking;
use App\Domains\Bar\Models\BarRestockingLine;
use App\Domains\Bar\Services\RestockingList;
use App\Domains\Bar\Services\StockService;
use App\Domains\ClubAdmin\ExpenseReports\Actions\StoreExpenseReportFiles;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Role;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Un bar qui a vécu : de quoi tester le réassort et les stats sans rien saisir.
 *
 * Décidé le 2026-09-26. Le seeder vide tout le bar puis le re-sème — on repart
 * toujours du même état, sans toucher au reste de la base :
 *
 * - une vingtaine de produits, avec leurs vrais conditionnements ;
 * - des soirées les mardis et jeudis (entraînements) et les vendredis
 *   (interclubs, deux fois plus de monde), une partie encaissée après minuit ;
 * - environ 21 semaines en arrière avec une fermeture d'été d'environ 9, les
 *   deux relatives au jour du semis — le « mois dernier » et les « 3 derniers
 *   mois » ont toujours une période précédente à comparer ;
 * - des offerts, et deux ardoises restées ouvertes ;
 * - trois tournées closes, dont une payée par Xavier avec sa note de frais ;
 * - un inventaire final qui laisse 4 produits « À acheter », 4 « Si vous avez
 *   la place », 3 sans max (donc 3 suggestions à reprendre), un produit jamais
 *   vendu, Coca Zero en hausse et Leffe en baisse.
 *
 * Tous les tirages partent d'une graine fixe, dans un générateur à soi : deux
 * lancements le même jour donnent la même base. Ni `fake()` ni `mt_rand()` — l'aléa
 * global est consommé ailleurs pendant le semis, et la séquence se décalait.
 */
class BarDemoSeeder extends Seeder
{
    /**
     * Le catalogue : catégorie, prix, conditionnement, ventes par soirée ordinaire
     * (avant l'été, après l'été), min, max, et le stock laissé par l'inventaire final.
     *
     * @var array<string, array{category: string, price: string, pack: int, label: string|null, before: float, after: float, min: int|null, max: int|null, final: int}>
     */
    private const array CATALOGUE = [
        'Jupiler' => ['category' => 'Bières', 'price' => '2.00', 'pack' => 24, 'label' => 'casier', 'before' => 12, 'after' => 12, 'min' => 24, 'max' => 72, 'final' => 10],
        'Jupiler 0.0' => ['category' => 'Bières', 'price' => '2.00', 'pack' => 6, 'label' => 'pack', 'before' => 2, 'after' => 2, 'min' => 6, 'max' => 18, 'final' => 12],
        'Leffe blonde' => ['category' => 'Bières', 'price' => '3.00', 'pack' => 24, 'label' => 'casier', 'before' => 6, 'after' => 1, 'min' => 12, 'max' => 48, 'final' => 48],
        'Chimay bleue' => ['category' => 'Bières', 'price' => '3.50', 'pack' => 24, 'label' => 'carton', 'before' => 2, 'after' => 2, 'min' => null, 'max' => null, 'final' => 15],
        'Duvel' => ['category' => 'Bières', 'price' => '3.50', 'pack' => 24, 'label' => 'carton', 'before' => 1.5, 'after' => 1.5, 'min' => null, 'max' => null, 'final' => 20],
        'Coca-Cola' => ['category' => 'Softs', 'price' => '2.00', 'pack' => 6, 'label' => 'pack', 'before' => 8, 'after' => 8, 'min' => 12, 'max' => 36, 'final' => 5],
        'Coca Zero' => ['category' => 'Softs', 'price' => '2.00', 'pack' => 6, 'label' => 'pack', 'before' => 2, 'after' => 6, 'min' => 6, 'max' => 24, 'final' => 20],
        'Fanta' => ['category' => 'Softs', 'price' => '2.00', 'pack' => 6, 'label' => 'pack', 'before' => 3, 'after' => 3, 'min' => 6, 'max' => 18, 'final' => 18],
        'Ice Tea' => ['category' => 'Softs', 'price' => '2.00', 'pack' => 6, 'label' => 'pack', 'before' => 4, 'after' => 4, 'min' => 6, 'max' => 24, 'final' => 24],
        'Eau plate' => ['category' => 'Softs', 'price' => '1.50', 'pack' => 6, 'label' => 'pack', 'before' => 5, 'after' => 5, 'min' => 12, 'max' => 36, 'final' => 30],
        'Eau pétillante' => ['category' => 'Softs', 'price' => '1.50', 'pack' => 6, 'label' => 'pack', 'before' => 0, 'after' => 0, 'min' => 6, 'max' => 12, 'final' => 12],
        'Aquarius' => ['category' => 'Softs', 'price' => '2.50', 'pack' => 1, 'label' => null, 'before' => 2, 'after' => 2, 'min' => 4, 'max' => 12, 'final' => 12],
        'Café' => ['category' => 'Chaud', 'price' => '1.50', 'pack' => 50, 'label' => 'boîte', 'before' => 6, 'after' => 6, 'min' => 20, 'max' => 100, 'final' => 12],
        'Thé' => ['category' => 'Chaud', 'price' => '1.50', 'pack' => 25, 'label' => 'boîte', 'before' => 1, 'after' => 1, 'min' => 10, 'max' => 50, 'final' => 45],
        'Chips sel' => ['category' => 'Snacks', 'price' => '1.50', 'pack' => 20, 'label' => 'carton', 'before' => 5, 'after' => 5, 'min' => 10, 'max' => 40, 'final' => 6],
        'Chips paprika' => ['category' => 'Snacks', 'price' => '1.50', 'pack' => 20, 'label' => 'carton', 'before' => 4, 'after' => 4, 'min' => 10, 'max' => 40, 'final' => 40],
        'Mars' => ['category' => 'Snacks', 'price' => '1.50', 'pack' => 32, 'label' => 'boîte', 'before' => 2, 'after' => 2, 'min' => 8, 'max' => 32, 'final' => 32],
        'Twix' => ['category' => 'Snacks', 'price' => '1.50', 'pack' => 32, 'label' => 'boîte', 'before' => 2, 'after' => 2, 'min' => 8, 'max' => 32, 'final' => 32],
        'Gaufre' => ['category' => 'Snacks', 'price' => '1.50', 'pack' => 1, 'label' => null, 'before' => 2, 'after' => 2, 'min' => 5, 'max' => 15, 'final' => 15],
        'Sandwich' => ['category' => 'Autres', 'price' => '3.50', 'pack' => 1, 'label' => null, 'before' => 1, 'after' => 1, 'min' => 2, 'max' => 6, 'final' => 6],
        "Balle d'entraînement" => ['category' => 'Autres', 'price' => '2.00', 'pack' => 6, 'label' => 'boîte', 'before' => 0.5, 'after' => 0.5, 'min' => null, 'max' => null, 'final' => 8],
    ];

    /** Les soirs d'ouverture : mardi et jeudi (entraînements), vendredi (interclubs). */
    private const array OPEN_DAYS = [CarbonImmutable::TUESDAY, CarbonImmutable::THURSDAY, CarbonImmutable::FRIDAY];

    private const int RANDOM_SEED = 20260926;

    /**
     * Trois tournées : quand (semaines après le début), qui, qui a payé, et ce qu'on a acheté.
     *
     * @var list<array{week: int, shopper: string, paid_by: string, packs: array<string, int>}>
     */
    private const array TRIPS = [
        ['week' => 3, 'shopper' => 'first', 'paid_by' => BarRestocking::PAID_BY_CLUB, 'packs' => ['Jupiler' => 3, 'Coca-Cola' => 4, 'Chips sel' => 2, 'Café' => 1]],
        ['week' => 6, 'shopper' => 'first', 'paid_by' => BarRestocking::PAID_BY_NOBODY, 'packs' => ['Leffe blonde' => 2, 'Eau plate' => 3, 'Fanta' => 2]],
        ['week' => 18, 'shopper' => 'xavier', 'paid_by' => BarRestocking::PAID_BY_ME, 'packs' => ['Jupiler' => 2, 'Coca Zero' => 3, 'Ice Tea' => 2, 'Mars' => 1]],
    ];

    private Randomizer $random;

    public function run(StockService $stockService): void
    {
        $this->random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $realNow = Carbon::getTestNow();
        $today = CarbonImmutable::now()->startOfDay();

        activity()->disableLogging();

        try {
            // Une seule transaction : sur MySQL, un commit par requête coûtait
            // 47 s pour 5 s de calcul.
            DB::transaction(function () use ($stockService, $today): void {
                $this->wipe();

                $products = $this->catalogue();
                $shoppers = $this->storeKeepers();

                $start = $today->subWeeks(21);
                $summerStart = $today->subWeeks(14);
                $summerEnd = $today->subWeeks(5);
                $evenings = $this->evenings($start, $today, $summerStart, $summerEnd);

                // L'inventaire d'ouverture couvre tout ce qui va se vendre : le FIFO ne doit
                // jamais manquer de lot, l'inventaire final ramène ensuite chaque produit
                // au stock décidé.
                $sales = $this->plannedSales($evenings, $summerEnd);
                $this->at($start->subDay()->setTime(10, 0));
                foreach ($products as $name => $product) {
                    $stockService->addIncomingStock($product->id, $sales['totals'][$name] + self::CATALOGUE[$name]['final'] + 48, 'Opening inventory');
                }

                $trips = collect(self::TRIPS)->keyBy(fn (array $trip): string => $start->addWeeks($trip['week'])->next(CarbonImmutable::SATURDAY)->toDateString());

                foreach ($evenings as $evening) {
                    foreach ($trips as $date => $trip) {
                        if ($date < $evening->toDateString()) {
                            $this->trip(CarbonImmutable::parse($date), $trip, $products, $shoppers, $stockService);
                            $trips->forget($date);
                        }
                    }

                    $this->evening($evening, $sales['evenings'][$evening->toDateString()], $products, $stockService, $evening->equalTo(end($evenings)));
                }

                // L'inventaire du matin : chaque produit sur le stock décidé.
                $this->at(CarbonImmutable::now()->min($today->setTime(9, 0)));
                foreach (BarProduct::query()->withStock()->get() as $product) {
                    $stockService->adjustStockTo($product->id, self::CATALOGUE[$product->name]['final'], $product->stock, 'Inventory count');
                }
            });
        } finally {
            Carbon::setTestNow($realNow);
            CarbonImmutable::setTestNow($realNow);
            activity()->enableLogging();
        }
    }

    /**
     * Faire comme si l'on était à ce moment-là : horodatages et FIFO suivent.
     */
    private function at(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    /**
     * @return array<string, BarProduct>
     */
    private function catalogue(): array
    {
        $categories = [];
        $products = [];

        foreach (self::CATALOGUE as $name => $row) {
            $categories[$row['category']] ??= BarCategory::query()->create(['name' => $row['category']]);

            $products[$name] = BarProduct::query()->create([
                'name' => $name,
                'category_id' => $categories[$row['category']]->id,
                'sale_price' => cents($row['price']),
                'is_available' => 1,
                'low_stock_threshold' => $row['min'],
                'max_stock' => $row['max'],
                'pack_size' => $row['pack'],
                'pack_label' => $row['label'],
            ]);
        }

        return $products;
    }

    /**
     * Une soirée : ses ventes réparties en commandes d'une à trois lignes.
     *
     * @param  array<string, int>  $quantities
     * @param  array<string, BarProduct>  $products
     */
    private function evening(CarbonImmutable $evening, array $quantities, array $products, StockService $stockService, bool $isLast): void
    {
        $lines = [];

        foreach ($quantities as $name => $quantity) {
            while ($quantity > 0) {
                $chunk = min($quantity, $this->random->getInt(1, 4));
                $lines[] = [$name, $chunk];
                $quantity -= $chunk;
            }
        }

        $orders = array_chunk($this->random->shuffleArray($lines), $this->random->getInt(1, 3));
        $count = count($orders);

        foreach ($orders as $index => $orderLines) {
            // De 19 h à 00 h 30 : la fin de soirée tombe sur la journée suivante.
            $time = $evening->setTime(19, 0)->addMinutes((int) round($index / max(1, $count - 1) * 330));
            $this->at($time);

            // Les deux dernières commandes de la dernière soirée restent des ardoises ouvertes.
            $open = $isLast && $index >= $count - 2;
            $offered = ! $open && $this->random->getInt(1, 15) === 1;
            $tabName = $open ? ($index === $count - 1 ? 'Équipe C' : 'Table 4') : null;

            $order = BarOrder::query()->create([
                'name' => $tabName,
                'open_name_key' => $open ? BarOrder::normaliseName($tabName) : null,
                'total_price' => 0,
                'is_paid' => $open ? 0 : 1,
                'paid_at' => $open ? null : $time,
                'payment_method' => $open ? null : ($offered ? 'offered' : 'cash'),
                'reason' => $offered ? 'Club visiteur' : null,
            ]);

            $total = 0;

            foreach ($orderLines as [$name, $quantity]) {
                $product = $products[$name];
                $item = BarOrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->sale_price,
                    'total_price' => $product->sale_price * $quantity,
                ]);
                $total += $product->sale_price * $quantity;

                $stockService->consumeFIFO($product->id, $quantity, "Order #{$order->id}", null, null, $order->id, $item->id);
            }

            $order->update(['total_price' => $total]);
        }
    }

    /**
     * Les soirs d'ouverture, hors fermeture d'été, jusqu'à hier.
     *
     * @return list<CarbonImmutable>
     */
    private function evenings(CarbonImmutable $start, CarbonImmutable $today, CarbonImmutable $summerStart, CarbonImmutable $summerEnd): array
    {
        $evenings = [];

        for ($day = $start; $day->lessThan($today); $day = $day->addDay()) {
            if (in_array($day->dayOfWeek, self::OPEN_DAYS, true) && ($day->lessThan($summerStart) || $day->greaterThanOrEqualTo($summerEnd))) {
                $evenings[] = $day;
            }
        }

        return $evenings;
    }

    /**
     * Ce qui se vend chaque soir, tiré d'avance : l'inventaire d'ouverture en dépend.
     *
     * Le vendredi compte double. Une soirée varie de 60 à 140 % de l'ordinaire.
     *
     * @param  list<CarbonImmutable>  $evenings
     * @return array{evenings: array<string, array<string, int>>, totals: array<string, int>}
     */
    private function plannedSales(array $evenings, CarbonImmutable $summerEnd): array
    {
        $plan = ['evenings' => [], 'totals' => array_fill_keys(array_keys(self::CATALOGUE), 0)];

        foreach ($evenings as $evening) {
            $factor = $evening->dayOfWeek === CarbonImmutable::FRIDAY ? 2 : 1;
            $period = $evening->greaterThanOrEqualTo($summerEnd) ? 'after' : 'before';

            foreach (self::CATALOGUE as $name => $row) {
                $quantity = (int) round($row[$period] * $factor * $this->random->getInt(60, 140) / 100);
                $plan['evenings'][$evening->toDateString()][$name] = $quantity;
                $plan['totals'][$name] += $quantity;
            }
        }

        return $plan;
    }

    /**
     * Xavier et le premier compte font les courses.
     *
     * @return array{first: User|null, xavier: User|null}
     */
    private function storeKeepers(): array
    {
        $shoppers = [
            'first' => User::query()->find(1) ?? User::query()->orderBy('id')->first(),
            'xavier' => User::query()->where('email', 'xavier.coenen@test.com')->first(),
        ];

        foreach (array_filter($shoppers) as $user) {
            if (! $user->hasRole(Role::STORE_KEEPER->value)) {
                $user->assignRole(Role::STORE_KEEPER->value);
            }
        }

        return $shoppers;
    }

    /**
     * Une tournée close : sa liste, ses entrées de stock, et la note de frais de qui a payé.
     *
     * @param  array{week: int, shopper: string, paid_by: string, packs: array<string, int>}  $plan
     * @param  array<string, BarProduct>  $products
     * @param  array{first: User|null, xavier: User|null}  $shoppers
     */
    private function trip(CarbonImmutable $date, array $plan, array $products, array $shoppers, StockService $stockService): void
    {
        $shopper = $shoppers[$plan['shopper']] ?? $shoppers['first'];

        if ($shopper === null) {
            return;
        }

        $this->at($date->setTime(9, 30));
        $trip = BarRestocking::query()->create([
            'status' => BarRestocking::STATUS_IN_PROGRESS,
            'shopper_id' => $shopper->id,
            'started_at' => now(),
        ]);

        $this->at($date->setTime(11, 0));
        $stocks = BarProduct::query()->withStock()->whereKey(array_map(fn (string $name): int => $products[$name]->id, array_keys($plan['packs'])))->get()->keyBy('id');

        foreach ($plan['packs'] as $name => $packs) {
            $product = $products[$name];
            $trip->lines()->create([
                'product_id' => $product->id,
                'section' => BarRestockingLine::SECTION_TO_BUY,
                'stock_at_start' => $stocks[$product->id]->stock,
                'pack_size' => $product->pack_size,
                'pack_label' => $product->pack_label,
                'proposed_packs' => $packs,
                'in_cart' => true,
                'bought_packs' => $packs,
            ]);
            $stockService->addIncomingStock($product->id, $packs * $product->pack_size, "Shopping trip #{$trip->id}", $shopper->id, $shopper->id, $trip->id);
        }

        $trip->update(['status' => BarRestocking::STATUS_CLOSED, 'closed_at' => now(), 'paid_by' => $plan['paid_by']]);

        if ($plan['paid_by'] === BarRestocking::PAID_BY_ME) {
            $report = ExpenseReport::query()->create([
                'user_id' => $shopper->id,
                'category' => ExpenseCategory::Bar,
                'description' => __('Bar shopping of :date: :items', [
                    'date' => $date->format('d/m/Y'),
                    'items' => collect($plan['packs'])->map(fn (int $packs, string $name): string => RestockingList::packsLabel($packs, $products[$name]->pack_size, $products[$name]->pack_label) . ' ' . $name)->join(', '),
                ]),
                'amount' => 87.40,
                'spent_on' => $date,
                'refund_iban' => $shopper->iban ?? 'BE68539007547034',
                'status' => ExpenseReportStatus::Submitted,
            ]);
            (new StoreExpenseReportFiles)($report, [UploadedFile::fake()->image('ticket-courses-bar.jpg', 600, 900)]);

            $trip->update(['expense_report_id' => $report->id]);
        }
    }

    /**
     * Vider le bar, et la note de frais que le semis précédent avait ouverte.
     */
    private function wipe(): void
    {
        $reportIds = BarRestocking::query()->whereNotNull('expense_report_id')->pluck('expense_report_id');

        DB::table('bar_restocking_lines')->delete();
        DB::table('bar_restockings')->delete();

        foreach ($reportIds as $reportId) {
            Storage::disk('local')->deleteDirectory("expense-reports/{$reportId}");
        }
        ExpenseReport::query()->whereKey($reportIds)->delete();

        Payment::query()->where('payable_type', (new BarOrder)->getMorphClass())->delete();
        DB::table('bar_stock_movements')->update(['source_movement_id' => null]);
        DB::table('bar_stock_movements')->delete();
        DB::table('bar_order_items')->delete();
        DB::table('bar_orders')->delete();
        DB::table('bar_products')->delete();
        DB::table('bar_categories')->delete();
    }
}
