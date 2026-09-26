<?php

declare(strict_types=1);

namespace App\Domains\Bar\Services;

use App\Domains\Bar\Models\BarProduct;
use App\Support\LocaleSort;

/**
 * Ce qu'il faut acheter pour remplir le bar, maintenant.
 *
 * Deux sections, parce que deux urgences :
 *
 * - « À acheter » : le stock est au min ou en dessous. C'est ce qui justifie une
 *   tournée — sans une ligne ici, personne n'a de raison d'aller au magasin ;
 * - « Si tu as la place » : le stock est entre le min et le max. Rien ne presse,
 *   mais qui est déjà au rayon s'épargne une tournée dans trois jours.
 *
 * La quantité remonte le stock au max, arrondie au conditionnement supérieur :
 * personne n'ouvre un casier au magasin, et le max est une cible, pas un plafond.
 * Un produit sans max est hors réassort et n'apparaît jamais.
 *
 * @phpstan-type RestockingLine array{product_id: int, name: string, category: string, stock: int, max: int, pack_size: int, pack_label: string|null, packs: int, units: int}
 */
class RestockingList
{
    /**
     * « 2 × casier », « 2 × 24 », ou « 7 » pour un produit qui s'achète à l'unité.
     *
     * Une seule écriture pour l'écran des courses et le digest du samedi.
     */
    public static function packsLabel(int $packs, int $packSize, ?string $packLabel): string
    {
        if ($packLabel !== null && $packLabel !== '') {
            return $packs . ' × ' . $packLabel;
        }

        return $packSize === 1 ? (string) $packs : $packs . ' × ' . $packSize;
    }

    /**
     * @return array{to_buy: list<RestockingLine>, if_room: list<RestockingLine>}
     */
    public function current(): array
    {
        $list = ['to_buy' => [], 'if_room' => []];

        $products = BarProduct::query()->withStock()->with('category')->whereNotNull('max_stock')->get();

        foreach ($products as $product) {
            $missing = (int) $product->max_stock - $product->stock;

            if ($missing <= 0) {
                continue;
            }

            $packSize = max(1, $product->pack_size);
            $packs = (int) ceil($missing / $packSize);
            $section = $product->is_low_stock ? 'to_buy' : 'if_room';

            $list[$section][] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name,
                'stock' => $product->stock,
                'max' => (int) $product->max_stock,
                'pack_size' => $packSize,
                'pack_label' => $product->pack_label,
                'packs' => $packs,
                'units' => $packs * $packSize,
            ];
        }

        // L'ordre du magasin, à peu près : par rayon, puis par nom. Deux tris
        // successifs, le tri de PHP étant stable : le second garde l'ordre du premier
        // à catégorie égale.
        foreach ($list as $section => $lines) {
            $byName = LocaleSort::by(collect($lines), fn (array $line): string => $line['name']);
            $list[$section] = array_values(LocaleSort::by($byName, fn (array $line): string => $line['category'])->all());
        }

        return $list;
    }
}
