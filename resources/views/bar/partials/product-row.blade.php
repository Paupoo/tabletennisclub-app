{{--
    Une ligne de produit dans la grille de commande.

    Partagée par les favoris et les catégories : les deux listes avaient divergé
    (les favoris n'offraient qu'un bouton « Ajouter », sans retrait possible).

    Attend `$product` et `$meta`, l'état résolu par bar/index.blade.php.
--}}
{{--
    En grille, `divide-y` ne sait plus séparer : chaque cellule porte donc sa
    propre bordure basse, et celles de la colonne de gauche une bordure droite.
--}}
<div @class([
    'border-base-200 flex items-center gap-3 border-b px-4 py-2.5 lg:odd:border-r',
    'opacity-60' => $meta->isUnavailable,
])>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold">{{ $product->name }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold tabular-nums">{{ euros($product->sale_price) }}</span>
            <span class="badge badge-sm font-bold {{ $meta->badgeClass }}">{{ $meta->badgeLabel }}</span>
        </div>
        @if ($meta->qty > 0 && ! $meta->isUnavailable && ! $meta->isStockLimit)
            <p class="text-subtle mt-0.5 text-xs tabular-nums">
                {{ $meta->theoreticalStock }} restant{{ $meta->theoreticalStock > 1 ? 's' : '' }}
                sur {{ $meta->realStock }}
            </p>
        @endif
    </div>

    {{-- Compteur — trois cibles de 44 px, manipulables d'une main. --}}
    <div class="border-base-300 bg-base-100 flex shrink-0 items-stretch overflow-hidden rounded-lg border">
        <form method="POST" action="{{ route('bar.cart.remove') }}">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button type="submit" class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
                @disabled($meta->qty === 0)
                aria-label="Retirer un {{ $product->name }}">
                <x-icon name="o-minus" class="h-4 w-4" />
            </button>
        </form>

        <span class="border-base-200 flex h-11 w-11 items-center justify-center border-x text-base font-bold tabular-nums">
            {{ $meta->qty }}
        </span>

        <form method="POST" action="{{ route('bar.cart.add') }}">
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">
            <button type="submit" class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
                @disabled($meta->disablePlus)
                aria-label="Ajouter un {{ $product->name }}">
                <x-icon name="o-plus" class="h-4 w-4" />
            </button>
        </form>
    </div>
</div>
