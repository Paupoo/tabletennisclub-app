<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarProduct;
use App\Domains\Bar\Models\BarRestockingAdjustment;
use Illuminate\Support\Collection;

/**
 * Ce que le digest du samedi raconte, en plus de la liste de courses.
 *
 * - ce que le réassort automatique a ajusté cette semaine ;
 * - les produits au réassort qui ne se vendent plus : l'automatique n'y touche
 *   pas, c'est au comité de décider de les retirer (décidé le 2026-09-27).
 */
class RestockingDigest
{
    public function __construct(private readonly RestockingSuggestions $suggestions) {}

    /**
     * Les ajustements des sept derniers jours, du plus ancien au plus récent.
     *
     * @return Collection<int, BarRestockingAdjustment>
     */
    public function adjustmentsOfTheWeek(): Collection
    {
        return BarRestockingAdjustment::query()
            ->with('product')
            ->where('created_at', '>=', now()->subWeek())
            ->orderBy('id')
            ->get();
    }

    /**
     * Les produits au réassort sans aucune vente sur les semaines observées.
     *
     * Un bar qui n'a encore rien vendu ne fait dormir personne : sans semaine
     * active, il n'y a rien à comparer.
     *
     * @return Collection<int, BarProduct>
     */
    public function sleepingProducts(): Collection
    {
        $suggestions = $this->suggestions->all();

        if ($suggestions === []) {
            return collect();
        }

        return BarProduct::query()
            ->whereNotNull('max_stock')
            ->whereKeyNot(array_keys($suggestions))
            ->orderBy('name')
            ->get();
    }
}
