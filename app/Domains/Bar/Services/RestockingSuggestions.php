<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrderItem;
use App\Domains\Bar\Models\BarProduct;
use Illuminate\Support\Collection;

/**
 * Ce que les ventes suggèrent comme min et max, produit par produit.
 *
 * Une suggestion, jamais un réglage : le magasinier l'applique ou non, parce
 * qu'il sait ce que les ventes ignorent — la place au frigo, une date de
 * péremption, une soirée à venir.
 *
 * La moyenne se prend sur les dernières semaines où le bar a vendu quelque
 * chose, pas sur les dernières semaines du calendrier : juillet et août fermés
 * diviseraient sinon les ventes de septembre par deux. Elle est pondérée — les
 * semaines récentes comptent davantage — pour qu'une tendance se suive. Un offert compte, il vide
 * le frigo autant qu'une vente ; une ardoise encore ouverte ne compte pas. Une perte
 * partie chez quelqu'un compte aussi ({@see BarLosses}) : il faudra la racheter.
 *
 * Pas d'arrondi au conditionnement : il se fait au moment d'acheter, et arrondir
 * ici donnerait min = max à tout produit lent vendu par carton.
 */
class RestockingSuggestions
{
    /**
     * Toutes les combien de semaines actives le poids d'une semaine est divisé par deux.
     *
     * Une vraie hausse se voit en deux ou trois semaines ; un vendredi exceptionnel,
     * noyé dans les onze autres, ne fait pas bondir le max.
     */
    public const int HALF_LIFE_WEEKS = 4;

    /** Combien de semaines actives on regarde. */
    public const int WEEKS_OBSERVED = 12;

    public function __construct(
        private readonly BarRestockingSettings $settings,
        private readonly BarLosses $losses,
    ) {}

    /**
     * Les suggestions des produits qui ont vendu, indexées par identifiant.
     *
     * @return array<int, array{min: int, max: int}>
     */
    public function all(): array
    {
        $sales = $this->paidSalesOfLastYear();

        $activeWeeks = $sales->pluck('week')->unique()->sortDesc()->take(self::WEEKS_OBSERVED)->values();

        if ($activeWeeks->isEmpty()) {
            return [];
        }

        // Le poids de chaque semaine active, la plus récente d'abord.
        $weights = $activeWeeks->mapWithKeys(fn (string $week, int $age): array => [$week => 0.5 ** ($age / self::HALF_LIFE_WEEKS)]);
        $totalWeight = $weights->sum();

        $products = BarProduct::query()->get(['id', 'restocking_weeks', 'restocking_cap'])->keyBy('id');
        $minWeeks = $this->settings->minWeeks();
        $maxWeeks = $this->settings->maxWeeks();

        // Ce que les inventaires ont trouvé parti chez quelqu'un s'ajoute aux ventes,
        // dans les semaines actives où il a été étalé.
        return $sales
            ->concat($this->losses->spreadDemand())
            ->whereIn('week', $activeWeeks->all())
            ->groupBy('product_id')
            ->filter(fn (Collection $rows, int $productId): bool => $products->has($productId))
            ->map(function (Collection $rows, int $productId) use ($weights, $totalWeight, $products, $minWeeks, $maxWeeks): array {
                // Arrondi au centième : 8 unités par semaine ne doivent pas devenir
                // 8,000000001 et un max de 25 au lieu de 24.
                $weekly = round($rows->sum(fn (array $row): float => $row['quantity'] * $weights[$row['week']]) / $totalWeight, 2);

                // Un périssable couvre moins de semaines que le bar ; le min suit.
                $product = $products[$productId];
                $productMaxWeeks = $product->restocking_weeks ?? $maxWeeks;
                $max = (int) ceil($weekly * $productMaxWeeks);
                $min = (int) ceil($weekly * min($minWeeks, $productMaxWeeks));

                // Le plafond est celui du frigo : ni le max ni le min ne le dépassent.
                if ($product->restocking_cap !== null) {
                    $max = min($max, $product->restocking_cap);
                    $min = min($min, $max);
                }

                return ['min' => $min, 'max' => $max];
            })
            ->all();
    }

    /**
     * Une ligne par article vendu, avec la semaine d'exploitation à laquelle il appartient.
     *
     * La semaine se calcule ici et non en SQL : SQLite (les tests) et MySQL
     * (la production) n'ont pas les mêmes fonctions de date.
     *
     * @return Collection<int, array{product_id: int, quantity: int, week: string}>
     */
    private function paidSalesOfLastYear(): Collection
    {
        return BarOrderItem::query()
            ->join('bar_orders', 'bar_orders.id', '=', 'bar_order_items.order_id')
            ->where('bar_orders.is_paid', 1)
            ->where('bar_orders.created_at', '>=', now()->subYear())
            ->get(['bar_order_items.product_id', 'bar_order_items.quantity', 'bar_orders.created_at as sold_at'])
            ->map(fn (BarOrderItem $item): array => [
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'week' => BarLosses::weekOf($item->getAttribute('sold_at')),
            ]);
    }
}
