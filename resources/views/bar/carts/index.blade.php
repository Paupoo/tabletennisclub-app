@php
    use App\Support\Breadcrumb;

    $trail = Breadcrumb::make()->home()->bar()->current('Panier')->toArray();
@endphp

<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$trail" separator="o-slash" />
    </x-slot:breadcrumbs>

    {{-- Le panier est le même objet que le ticket. --}}
    <div class="max-w-2xl space-y-4">

        <div>
            <a href="{{ route('bar.index') }}" class="text-primary tap-min mb-2 inline-flex gap-1.5 text-sm font-semibold hover:underline">
                <x-icon name="o-arrow-left" class="h-4 w-4" />
                Retour à la commande
            </a>
            <h1 class="text-2xl font-bold tracking-tight">Panier</h1>
            <p class="text-muted mt-1">Vérifiez les articles avant de valider.</p>
        </div>

        @if ($items->isEmpty())
            <x-card class="shadow-sm">
                <div class="flex flex-col items-center gap-4 py-8 text-center">
                    <x-icon name="o-shopping-cart" class="text-base-content/25 h-12 w-12" />
                    <div>
                        <p class="font-semibold">Votre panier est vide.</p>
                        <p class="text-muted mt-1 text-sm">Ajoutez des produits pour démarrer une commande.</p>
                    </div>
                    <a href="{{ route('bar.index') }}" class="btn btn-primary tap-comfort gap-2">
                        <x-icon name="o-plus" class="h-4 w-4" />
                        Choisir des produits
                    </a>
                </div>
            </x-card>
        @else
            <x-card class="shadow-sm">
                <div class="divide-base-200 divide-y">
                    @foreach ($items as $item)
                        @php
                            $product = $item['product'];
                            $qty = $item['quantity'];
                            $realStock = (int) $product->stock;
                            $isStockLimit = $qty >= $realStock;
                        @endphp

                        <div class="flex items-center gap-3 py-3 first:pt-0">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold">{{ $product->name }}</p>
                                @if ($isStockLimit)
                                    <p class="text-warning mt-0.5 flex items-center gap-1.5 text-xs font-semibold">
                                        <x-icon name="o-exclamation-triangle" class="h-3.5 w-3.5" />
                                        Stock maximum atteint
                                    </p>
                                @else
                                    <p class="text-subtle mt-0.5 text-xs tabular-nums">
                                        {{ euros($product->sale_price) }} l'unité · {{ $realStock }} en stock
                                    </p>
                                @endif
                            </div>

                            <div class="border-base-300 bg-base-100 flex shrink-0 items-stretch overflow-hidden rounded-lg border">
                                <form method="POST" action="{{ route('bar.cart.remove') }}">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <button type="submit" class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
                                        @disabled($qty === 0)
                                        aria-label="Retirer un {{ $product->name }}">
                                        <x-icon name="o-minus" class="h-4 w-4" />
                                    </button>
                                </form>

                                <span class="border-base-200 flex h-11 w-11 items-center justify-center border-x text-base font-bold tabular-nums">
                                    {{ $qty }}
                                </span>

                                <form method="POST" action="{{ route('bar.cart.add') }}">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <button type="submit" class="tap-comfort text-primary disabled:text-base-content/30 h-11 w-11"
                                        @disabled($isStockLimit)
                                        aria-label="Ajouter un {{ $product->name }}">
                                        <x-icon name="o-plus" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>

                            <span class="w-20 shrink-0 text-end text-sm font-bold tabular-nums">
                                {{ euros($item['total_price']) }}
                            </span>
                        </div>
                    @endforeach
                </div>

                {{-- Total --}}
                <div class="border-base-content mt-4 flex items-center justify-between border-t-2 pt-4">
                    <span class="text-muted text-xs font-bold uppercase tracking-widest">Total</span>
                    <span class="text-3xl font-black tabular-nums tracking-tight">{{ euros($totalPrice) }}</span>
                </div>

                {{-- Actions --}}
                <div class="mt-4 space-y-2.5">
                    <div class="flex flex-wrap gap-2.5">
                        <form method="POST" action="{{ route('bar.cart.clear') }}" class="min-w-[9rem] flex-1">
                            @csrf
                            <button type="submit" class="btn btn-outline btn-error tap-comfort w-full gap-2">
                                <x-icon name="o-trash" class="h-4 w-4" />
                                Vider le panier
                            </button>
                        </form>

                        <form method="POST" action="{{ route('bar.cart.validate') }}" class="min-w-[9rem] flex-1">
                            @csrf
                            <button type="submit" name="action" value="validate" class="btn btn-primary tap-comfort w-full gap-2">
                                <x-icon name="o-check" class="h-4 w-4" />
                                Valider la commande
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('bar.cart.validate') }}">
                        @csrf
                        <button type="submit" name="action" value="pay_now" class="btn btn-secondary tap-comfort w-full gap-2">
                            <x-icon name="o-credit-card" class="h-4 w-4" />
                            Payer maintenant
                        </button>
                    </form>
                </div>
            </x-card>
        @endif

    </div>
</x-app-layout>
