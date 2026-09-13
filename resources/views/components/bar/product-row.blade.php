@props(['product', 'state'])

{{--
    Une ligne de produit dans la grille de commande.

    Partagée par les favoris et les rayons : les deux listes avaient divergé, les
    favoris n'offrant qu'un « + » sans retrait possible.

    En grille, `divide-y` ne sait plus séparer : chaque cellule porte donc sa propre
    bordure basse, et celles de la colonne de gauche une bordure droite.
--}}
<div
    wire:key="row-{{ $product->id }}"
    @class([
        'border-base-300 flex items-center gap-3 border-b px-4 py-2.5 lg:odd:border-r',
        'opacity-60' => $state->isUnavailable,
    ])
>
    <div class="min-w-0 flex-1">
        <p class="truncate text-sm font-semibold">{{ $product->name }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold tabular-nums">{{ euros($product->sale_price) }}</span>
            <span class="badge badge-sm font-bold {{ $state->badgeClass }}">{{ $state->badgeLabel }}</span>
        </div>
        @if ($state->qty > 0 && ! $state->isUnavailable && ! $state->isStockLimit)
            <p class="text-subtle mt-0.5 text-xs tabular-nums">
                {{ $state->theoreticalStock }} restant{{ $state->theoreticalStock > 1 ? 's' : '' }}
                sur {{ $state->realStock }}
            </p>
        @endif
    </div>

    {{--
        Le compteur — trois cibles de 44 px, manipulables d'une main.

        `wire:click` et non un formulaire : le compteur se met à jour sur place, sans
        rechargement, sans perte de la position de défilement, et sans les ~100 requêtes
        que coûtait un aller-retour complet.
    --}}
    <div class="border-base-300 bg-base-100 flex shrink-0 items-stretch overflow-hidden rounded-lg border">
        <button type="button"
            wire:click="remove({{ $product->id }})"
            class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
            @disabled($state->qty === 0)
            aria-label="Retirer un {{ $product->name }}">
            <x-icon name="o-minus" class="h-4 w-4" />
        </button>

        <span class="border-base-300 flex h-11 w-11 items-center justify-center border-x text-base font-bold tabular-nums">
            {{ $state->qty }}
        </span>

        <button type="button"
            wire:click="add({{ $product->id }})"
            class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
            @disabled($state->disablePlus)
            aria-label="Ajouter un {{ $product->name }}">
            <x-icon name="o-plus" class="h-4 w-4" />
        </button>
    </div>
</div>
