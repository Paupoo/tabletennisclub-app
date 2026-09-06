@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()->current('Produits')->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    <div class="space-y-4">

        <div>
            <h1 class="text-2xl font-bold tracking-tight">Produits</h1>
            <p class="text-muted mt-1">{{ __('Create a product, modify the price and set the total stock.') }}</p>
        </div>

        @if ($errors->any())
            <div role="alert" class="alert alert-error">
                <x-icon name="o-x-circle" class="h-5 w-5" />
                <ul class="list-inside list-disc text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Création --}}
        <details class="border-base-300 bg-base-100 group rounded-xl border" {{ session('open_product_panel') ? 'open' : '' }}>
            <summary class="tap-comfort w-full cursor-pointer list-none justify-start gap-2.5 px-4 py-3 text-sm font-bold [&::-webkit-details-marker]:hidden">
                <x-icon name="o-plus" class="text-primary h-5 w-5" />
                Ajouter un produit
                <x-icon name="o-chevron-down" class="text-base-content/50 ms-auto h-4 w-4 transition-transform group-open:rotate-180" />
            </summary>

            <div class="border-base-300 border-t p-4">
                <form id="product-form" method="POST" action="{{ route('bar.products.store') }}" class="space-y-3">
                    @csrf

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="label" for="product_name">
                                <span class="label-text text-xs font-semibold">Nom</span>
                            </label>
                            <input id="product_name" type="text" name="product_name" required
                                value="{{ old('product_name', $savedForm['product_name'] ?? '') }}"
                                placeholder="ex. Eau plate 50 cl"
                                class="input input-bordered tap-comfort w-full">
                        </div>

                        <div>
                            <label class="label" for="category_id_create">
                                <span class="label-text text-xs font-semibold">Catégorie</span>
                            </label>
                            <div class="flex gap-2">
                                <select id="category_id_create" name="category_id" required
                                    class="select select-bordered tap-comfort w-full">
                                    <option value="">Choisir une catégorie</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(
                                            session('selected_category_id') == $category->id
                                            || (! session('selected_category_id')
                                                && old('category_id', $savedForm['category_id'] ?? '') == $category->id)
                                        )>{{ $category->name }}</option>
                                    @endforeach
                                </select>

                                {{-- Passe par storeState() pour ne pas perdre la saisie en cours. --}}
                                <a href="{{ route('bar.categories.index') }}" onclick="return goToCategoryPage()"
                                    class="btn btn-outline tap-comfort shrink-0 px-3"
                                    aria-label="{{ __('Add a category') }}">
                                    <x-icon name="o-plus" class="h-4 w-4" />
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                        <div>
                            <label class="label" for="sale_price">
                                <span class="label-text text-xs font-semibold">Prix (€)</span>
                            </label>
                            <input id="sale_price" type="text" name="sale_price" required inputmode="decimal"
                                value="{{ old('sale_price', $savedForm['sale_price'] ?? '') }}"
                                placeholder="2,50" class="input input-bordered tap-comfort w-full">
                        </div>

                        <div>
                            <label class="label" for="stock">
                                <span class="label-text text-xs font-semibold">Stock</span>
                            </label>
                            <input id="stock" type="number" name="stock" min="0"
                                value="{{ old('stock', $savedForm['stock'] ?? '') }}"
                                class="input input-bordered tap-comfort w-full">
                        </div>

                        <label class="tap-comfort col-span-2 cursor-pointer justify-start gap-2.5 text-sm font-semibold sm:col-span-1">
                            <input type="hidden" name="is_available" value="0">
                            <input type="checkbox" name="is_available" value="1" class="toggle toggle-primary"
                                @checked(old('is_available', $savedForm['is_available'] ?? 1))>
                            Disponible
                        </label>
                    </div>

                    <button type="submit" class="btn btn-primary tap-comfort gap-2">
                        <x-icon name="o-bookmark-square" class="h-4 w-4" />
                        Créer le produit
                    </button>
                </form>
            </div>
        </details>

        {{-- Liste par catégorie --}}
        @foreach ($categories as $category)
            @php
                $products = $category->products ?? collect();
            @endphp

            <section class="space-y-3">
                <h2 class="text-muted text-xs font-bold uppercase tracking-widest">
                    {{ $category->name }}
                    <span class="text-subtle tabular-nums">· {{ $products->count() }} produit{{ $products->count() > 1 ? 's' : '' }}</span>
                </h2>

                @if ($products->isEmpty())
                    <p class="text-muted text-sm">{{ __('No products in this category.') }}</p>
                @else
                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach ($products as $product)
                            @php
                                $stock = (int) ($product->stock ?? $product->total_stock ?? 0);
                                $isLowStock = $stock <= 3;
                            @endphp

                            <x-card class="shadow-sm" x-data="{ confirming: false }">
                                <div class="mb-3 flex items-start gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-semibold">{{ $product->name }}</p>
                                        <p class="text-subtle text-xs">{{ $category->name }}</p>
                                    </div>
                                    <span @class([
                                        'badge badge-sm shrink-0 font-bold tabular-nums',
                                        'badge-error badge-soft' => $stock === 0,
                                        'badge-warning badge-soft' => $isLowStock && $stock > 0,
                                        'badge-success badge-soft' => ! $isLowStock,
                                    ])>{{ $stock }} en stock</span>
                                </div>

                                {{--
                                    Le formulaire n'enveloppe que les champs modifiables : le bouton
                                    d'enregistrement le vise par son attribut `form`, ce qui lui permet
                                    de voisiner les contrôles de suppression sans y être imbriqué.
                                --}}
                                <form id="product-update-{{ $product->id }}" method="POST"
                                    action="{{ route('bar.products.update', $product) }}">
                                    @csrf
                                    @method('PUT')

                                    <div class="grid grid-cols-2 items-end gap-3 sm:grid-cols-[1fr_1fr_auto]">
                                        <div>
                                            <label class="label" for="price-{{ $product->id }}">
                                                <span class="label-text text-xs font-semibold">Prix (€)</span>
                                            </label>
                                            <input id="price-{{ $product->id }}" name="sale_price" inputmode="decimal"
                                                value="{{ number_format($product->sale_price / 100, 2, ',', '') }}"
                                                class="input input-bordered tap-comfort w-full">
                                        </div>

                                        <div>
                                            <label class="label" for="stock-{{ $product->id }}">
                                                <span class="label-text text-xs font-semibold">Stock</span>
                                            </label>
                                            <input id="stock-{{ $product->id }}" type="number" min="0" name="stock"
                                                value="{{ $stock }}" class="input input-bordered tap-comfort w-full">
                                        </div>

                                        <label class="tap-comfort col-span-2 cursor-pointer justify-start gap-2.5 text-sm font-semibold sm:col-span-1">
                                            {{-- Garantit l'envoi de 0 quand la case est décochée. --}}
                                            <input type="hidden" name="is_available" value="0">
                                            <input type="checkbox" name="is_available" value="1" class="toggle toggle-primary"
                                                @checked((int) $product->is_available === 1)>
                                            Dispo
                                        </label>
                                    </div>
                                </form>

                                <div class="border-base-200 mt-3 border-t pt-3">
                                    <div class="flex gap-2" x-show="! confirming">
                                        <button type="submit" form="product-update-{{ $product->id }}"
                                            class="btn btn-primary btn-sm tap-comfort flex-1 gap-2">
                                            <x-icon name="o-bookmark-square" class="h-4 w-4" />
                                            Enregistrer
                                        </button>

                                        @if ($stock > 0)
                                            <button type="button" disabled
                                                class="btn btn-outline btn-error btn-sm tap-comfort flex-1 gap-2"
                                                title="{{ __('Stock is not empty: cannot delete') }}">
                                                <x-icon name="o-trash" class="h-4 w-4" />
                                                Supprimer
                                            </button>
                                        @else
                                            <button type="button" @click="confirming = true"
                                                class="btn btn-outline btn-error btn-sm tap-comfort flex-1 gap-2">
                                                <x-icon name="o-trash" class="h-4 w-4" />
                                                Supprimer
                                            </button>
                                        @endif
                                    </div>

                                    @if ($stock === 0)
                                        <div x-show="confirming" x-cloak x-collapse>
                                            <div role="alert" class="alert alert-warning mb-2.5 py-2 text-xs">
                                                <x-icon name="o-exclamation-triangle" class="h-4 w-4" />
                                                <span>Supprimer «&nbsp;{{ $product->name }}&nbsp;»&nbsp;? Cette action est définitive.</span>
                                            </div>

                                            <div class="flex gap-2">
                                                <form method="POST" action="{{ route('bar.products.destroy', $product) }}" class="flex-1">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-error btn-sm tap-comfort w-full gap-2">
                                                        <x-icon name="o-check" class="h-4 w-4" />
                                                        Oui, supprimer
                                                    </button>
                                                </form>

                                                <button type="button" @click="confirming = false"
                                                    class="btn btn-outline btn-sm tap-comfort flex-1 gap-2">
                                                    <x-icon name="o-x-mark" class="h-4 w-4" />
                                                    Annuler
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                @endif
            </section>
        @endforeach

    </div>

    <script>
    /**
     * Met la saisie en cours de côté avant de partir créer une catégorie, pour la
     * retrouver au retour. On navigue même si l'enregistrement échoue.
     */
    async function goToCategoryPage() {
        const form = document.getElementById('product-form');
        const data = new FormData(form);

        const body = {};
        data.forEach((v, k) => {
            if (k !== '_token') body[k] = v;
        });

        // Case décochée = absente de FormData, on force à 0
        if (!body.is_available) body.is_available = 0;

        try {
            await fetch("{{ route('bar.products.storeState') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        || '{{ csrf_token() }}',
                },
                body: JSON.stringify(body),
            });
        } catch (e) {
            // On navigue quand même si le fetch échoue
        }

        window.location = "{{ route('bar.categories.index') }}";
        return false;
    }
    </script>
</x-app-layout>
