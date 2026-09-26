<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarOrderItem;
use Illuminate\Support\Carbon;
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
 * le frigo autant qu'une vente ; une ardoise encore ouverte ne compte pas.
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

    /** … et le max trois : environ deux semaines entre deux tournées. */
    public const int MAX_WEEKS = 3;

    /** Le min couvre une semaine de ventes… */
    public const int MIN_WEEKS = 1;

    /** Combien de semaines actives on regarde. */
    public const int WEEKS_OBSERVED = 12;

    /**
     * La journée d'exploitation commence à 6 h : une vente à 1 h du matin appartient
     * à la soirée de la veille — la même frontière que la feuille de caisse.
     */
    private const int BUSINESS_DAY_STARTS_AT = 6;

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

        return $sales
            ->whereIn('week', $activeWeeks->all())
            ->groupBy('product_id')
            ->map(function (Collection $rows) use ($weights, $totalWeight): array {
                // Arrondi au centième : 8 unités par semaine ne doivent pas devenir
                // 8,000000001 et un max de 25 au lieu de 24.
                $weekly = round($rows->sum(fn (array $row): float => $row['quantity'] * $weights[$row['week']]) / $totalWeight, 2);

                return [
                    'min' => (int) ceil($weekly * self::MIN_WEEKS),
                    'max' => (int) ceil($weekly * self::MAX_WEEKS),
                ];
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
                'week' => Carbon::parse($item->getAttribute('sold_at'))
                    ->subHours(self::BUSINESS_DAY_STARTS_AT)
                    ->startOfWeek()
                    ->toDateString(),
            ]);
    }
}
