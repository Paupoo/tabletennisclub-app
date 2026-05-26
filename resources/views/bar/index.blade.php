@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>🍹 Bar - Commande</h1>
    <p class="muted">Sélectionnez les produits à ajouter à la commande.</p>
</div>
<div class="cart-bar" style="margin:14px; display:flex; align-items:center; justify-content:space-between;">

    {{-- LEFT PART --}}
    @if($cartCount > 0)
        <span>
            <strong>{{ $cartCount }}</strong> article(s)
        </span>
    @else
        <span class="muted">
            Panier vide
        </span>
    @endif

    {{-- RIGHT PART --}}
    <div style="display:flex; gap:10px; align-items:center;">

        {{-- ✅ CANCEL must ONLY depend on editing state --}}
        @if(session('editing_order_id'))
             <form method="POST" action="{{ route('bar.orders.cancelEdit')}}">
                @csrf
                <button type="submit" class="btn btn-clear">
                    <span class="icon-cross" style="font-size: 24px;">❌</span> Annuler modification
                </button>
            </form>
        @endif

        {{-- ✅ Cart button only if cart has content --}}
        @if($cartCount > 0)
            <a href="{{ route('bar.cart.show') }}" class="btn-cart">
                🛒 Voir la commande
            </a>
        @endif

    </div>

</div>

{{-- FAVORITES SECTION --}}
@if(isset($favorites) && $favorites->isNotEmpty())
<section class="panel" style="margin:14px">
    <details class="collapsible" data-category-id="favorites">
        <summary class="collapsible-summary">
            <span class="accordion-title">⭐ Favoris</span>
        </summary>
        <div class="accordion-body">
            <div class="products-grid">
                @foreach($favorites as $product)
                @php
                $stock = (int) $product->stock;
                $qtyInCart = $cart[$product->id] ?? 0;
                $stockDisplay = max(0, $stock - $qtyInCart);
                $disablePlus = !$product->is_available || ($stockDisplay === 0);
                @endphp
                
                <div class="product-card">
                    <div class="product-card-top">
                        <div>
                            <p class="product-card-name">{{ $product->name }}</p>
                        </div>
                        <span class="stock-label">{{ $stockDisplay }} en stock</span>
                    </div>
                    
                    <form method="POST" action="{{ route('bar.cart.add') }}">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <button class="btn btn-pay btn-block" {{ $disablePlus ? 'disabled' : '' }}>➕ Ajouter</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
    </details>
</section>
@endif
@foreach ($categories as $category)

<section class="panel" style="margin: 14px;">

    <details class="collapsible" data-category-id="{{ $category->id }}">
        <summary class="collapsible-summary">
            <span class="accordion-title">{{ $category->name }}</span>
        </summary>

        <div class="accordion-body">

            @forelse ($category->products as $product)

                @php
                    $stock = (int) $product->stock;
                    $qtyInCart = $cart[$product->id] ?? 0;
                    $stockDisplay = max(0, $stock - $qtyInCart);

                    $isUnavailable = !$product->is_available;
                    $rowUnavailable = $isUnavailable || ($stockDisplay === 0 && $qtyInCart === 0);
                    $disablePlus = $isUnavailable || ($stockDisplay === 0);
                @endphp

                <div class="product-row {{ $rowUnavailable ? 'product-row--unavailable' : '' }}">

                    <div class="product-info">
                        <span class="product-name">{{ $product->name }}</span>
                        <span class="product-price">{{ euros($product->sale_price) }}</span>

                        @if($isUnavailable)
                            <span class="stock-label stock-label--unavailable">Indisponible</span>
                        @elseif($stockDisplay === 0)
                            <span class="stock-label stock-label--empty">Rupture de stock</span>
                        @elseif($stockDisplay <= 3)
                            <span class="stock-label stock-label--low">Plus que {{ $stockDisplay }}</span>
                        @else
                            <span class="stock-label stock-label--ok">{{ $stockDisplay }} en stock</span>
                        @endif
                    </div>

                    <div class="product-controls">

                        <form method="POST" action="{{ route('bar.cart.remove') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="btn-qty {{ $qtyInCart === 0 ? 'btn-qty--disabled' : '' }}" {{ $qtyInCart === 0 ? 'disabled' : '' }}>
                                −
                            </button>
                        </form>

                        <span class="qty-val">{{ $qtyInCart }}</span>

                        <form method="POST" action="{{ route('bar.cart.add') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="btn-qty" {{ $disablePlus ? 'disabled' : '' }}>
                                +
                            </button>
                        </form>

                    </div>

                </div>

            @empty
                <p class="muted">Aucun produit dans cette catégorie.</p>
            @endforelse

        </div>

    </details>

</section>

@endforeach

{{-- ✅ Accordion state persistence --}}
<script>
document.addEventListener('DOMContentLoaded', () => {
    const accordions = document.querySelectorAll('.collapsible');

    accordions.forEach(el => {
        const id = el.dataset.categoryId;

        const isOpen = localStorage.getItem('accordion_' + id);
        if (isOpen === 'true') {
            el.open = true;
        }

        el.addEventListener('toggle', () => {
            localStorage.setItem('accordion_' + id, el.open);
        });
    });
});
</script>

@endsection
