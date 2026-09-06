@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()->current('Nouvelle commande')->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    @php
        /**
         * État d'affichage d'un produit dans la grille de commande.
         *
         * Le libellé de stock porte quatre sens différents (indisponible, rupture,
         * plafond du panier atteint, stock bas) et chacun change la couleur du badge
         * et l'activation du « + ». Les résoudre en un seul endroit évite que les
         * favoris et les catégories divergent, comme c'était le cas.
         *
         * @return object{qty:int, realStock:int, theoreticalStock:int, isUnavailable:bool,
         *                isStockLimit:bool, disablePlus:bool, badgeClass:string, badgeLabel:string}
         */
        $resolveProductMeta = function ($product) use ($cart) {
            $qty = $cart[$product->id] ?? 0;
            $realStock = (int) $product->stock;
            $theoreticalStock = max(0, $realStock - $qty);
            $isUnavailable = ! $product->is_available;
            $isStockLimit = $qty >= $realStock;
            $disablePlus = $isUnavailable || $isStockLimit;

            $badgeClass = 'badge-success badge-soft';
            $badgeLabel = $realStock . ' en stock';

            if ($isUnavailable) {
                $badgeClass = 'badge-ghost';
                $badgeLabel = 'Indisponible';
            } elseif ($realStock === 0) {
                $badgeClass = 'badge-error badge-soft';
                $badgeLabel = 'Rupture de stock';
            } elseif ($isStockLimit) {
                $badgeClass = 'badge-warning badge-soft';
                $badgeLabel = 'Stock maximum atteint';
            } elseif ($realStock <= 3) {
                $badgeClass = 'badge-warning badge-soft';
                $badgeLabel = 'Plus que ' . $realStock;
            }

            return (object) compact(
                'qty', 'realStock', 'theoreticalStock',
                'isUnavailable', 'isStockLimit',
                'disablePlus', 'badgeClass', 'badgeLabel'
            );
        };
    @endphp

    {{--
        Le catalogue passe à deux colonnes dès `lg`. En une seule colonne étirée,
        500 px séparaient le nom du produit de son compteur ; et servir trente
        références demandait deux fois plus de défilement.
    --}}
    <div class="max-w-5xl space-y-4 pb-24">

        {{-- En-tête --}}
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Nouvelle commande</h1>
            <p class="text-muted mt-1">Sélectionnez les produits à ajouter.</p>
        </div>

        {{-- Favoris — les produits les plus vendus, toujours à portée de pouce --}}
        @if (isset($favorites) && $favorites->isNotEmpty())
            <details class="border-base-300 bg-base-100 group rounded-xl border" data-category-id="favorites">
                <summary class="tap-comfort w-full cursor-pointer list-none justify-start gap-2.5 px-4 py-3 text-sm font-bold [&::-webkit-details-marker]:hidden">
                    <x-icon name="o-star" class="text-secondary h-5 w-5" />
                    Favoris
                    <span class="badge badge-ghost badge-sm ms-auto font-bold tabular-nums">{{ $favorites->count() }}</span>
                    <x-icon name="o-chevron-down" class="text-base-content/50 h-4 w-4 transition-transform group-open:rotate-180" />
                </summary>

                <div class="border-base-300 border-t lg:grid lg:grid-cols-2">
                    @foreach ($favorites as $product)
                        @include('bar.partials.product-row', ['product' => $product, 'meta' => $resolveProductMeta($product)])
                    @endforeach
                </div>
            </details>
        @endif

        {{-- Catégories --}}
        @foreach ($categories as $category)
            <details class="border-base-300 bg-base-100 group rounded-xl border" data-category-id="{{ $category->id }}">
                <summary class="tap-comfort w-full cursor-pointer list-none justify-start gap-2.5 px-4 py-3 text-sm font-bold [&::-webkit-details-marker]:hidden">
                    <x-icon name="o-cube" class="text-primary h-5 w-5" />
                    {{ $category->name }}
                    <span class="badge badge-ghost badge-sm ms-auto font-bold tabular-nums">{{ $category->products->count() }}</span>
                    <x-icon name="o-chevron-down" class="text-base-content/50 h-4 w-4 transition-transform group-open:rotate-180" />
                </summary>

                <div class="border-base-300 border-t lg:grid lg:grid-cols-2">
                    @forelse ($category->products as $product)
                        @include('bar.partials.product-row', ['product' => $product, 'meta' => $resolveProductMeta($product)])
                    @empty
                        <p class="text-muted px-4 py-4 text-sm">Aucun produit dans cette catégorie.</p>
                    @endforelse
                </div>
            </details>
        @endforeach

    </div>

    {{-- Mémorisation des accordéons ouverts, d'une commande à l'autre. --}}
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const accordions = document.querySelectorAll('details[data-category-id]');

        // Restauration de l'état enregistré. Sans préférence connue, la catégorie
        // s'ouvre : un point de vente qui démarre entièrement replié n'affiche
        // qu'une pile de barres grises, et il faut un tap par catégorie avant
        // de pouvoir servir quoi que ce soit.
        accordions.forEach(el => {
            const stored = localStorage.getItem('accordion_' + el.dataset.categoryId);
            el.open = stored === null ? true : stored === 'true';
        });

        // Un seul écouteur délégué plutôt qu'un par accordéon.
        // 'toggle' ne remonte pas, mais la délégation en phase de capture fonctionne.
        document.addEventListener('toggle', (e) => {
            const el = e.target;
            if (el instanceof HTMLDetailsElement && el.dataset.categoryId) {
                localStorage.setItem('accordion_' + el.dataset.categoryId, el.open);
            }
        }, true);
    });
    </script>
    {{--
        Panier en cours — pilule flottante.

        Servir au bar, c'est faire défiler un catalogue en ajoutant au fil de
        l'eau. Une barre de validation posée en haut de page remonte hors de vue
        dès le deuxième produit, et il faut refaire le chemin inverse à chaque
        tournée. La pilule reste sous le pouce.

        Même motif que <x-admin.shared.selection-pill> : `pointer-events-none`
        sur le conteneur pleine largeur, réactivé sur la pilule elle-même, pour
        que le reste de la page reste cliquable au travers.

        Elle n'existe que si le panier n'est pas vide — au repos, rien ne flotte.
        Le `pb-24` du conteneur au-dessus lui réserve sa place : sans lui, elle
        recouvrirait le dernier produit de la dernière catégorie.
    --}}
    @if ($cartCount > 0 || session('editing_order_id'))
        <div class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4">
            <div class="border-base-300 bg-base-100 pointer-events-auto flex max-w-2xl flex-wrap items-center gap-3 rounded-2xl border px-4 py-2.5 shadow-2xl">

                @if ($cartCount > 0)
                    <p class="text-sm">
                        <span class="font-bold tabular-nums">{{ $cartCount }}</span>
                        article{{ $cartCount > 1 ? 's' : '' }}
                        <span class="text-subtle">·</span>
                        <span class="font-bold tabular-nums">{{ euros($totalPrice) }}</span>
                    </p>
                @else
                    <p class="text-muted text-sm">Panier vide</p>
                @endif

                @if (session('editing_order_id'))
                    <form method="POST" action="{{ route('bar.orders.cancelEdit') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm tap-comfort gap-2">
                            <x-icon name="o-x-mark" class="h-4 w-4" />
                            Annuler la modification
                        </button>
                    </form>
                @endif

                @if ($cartCount > 0)
                    <a href="{{ route('bar.cart.show') }}" class="btn btn-primary btn-sm tap-comfort gap-2">
                        <x-icon name="o-shopping-cart" class="h-4 w-4" />
                        Voir la commande
                    </a>
                @endif
            </div>
        </div>
    @endif

</x-app-layout>
