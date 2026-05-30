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
    <div class="cart-bar__actions" style="display:flex; gap:10px; align-items:center;">

        {{-- CANCEL must ONLY depend on editing state --}}
        @if(session('editing_order_id'))
             <form method="POST" action="{{ route('bar.orders.cancelEdit')}}">
                @csrf
                <button type="submit" class="btn btn-clear">
                    <span class="icon-cross" style="font-size: 24px;">❌</span> Annuler modification
                </button>
            </form>
        @endif

        {{-- Cart button only if cart has content --}}
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
                    $qty = $cart[$product->id] ?? 0;
                    $realStock = (int) $product->stock;
                    $theoreticalStock = max(0, $realStock - $qty);
                    $maxQty = 20;
                    $isUnavailable = !$product->is_available;
                    $isStockLimit = $qty >= $realStock;
                    $isMaxLimit = $qty >= $maxQty;
                    $disablePlus = $isUnavailable || $isStockLimit || $isMaxLimit;

                    $labelClass = 'stock-label--ok';
                    $labelText = $realStock . ' en stock';
                    
                    if ($isUnavailable) {
                        $labelClass = 'stock-label--unavailable';
                        $labelText = 'Indisponible';
                    } elseif ($realStock === 0) {
                        $labelClass = 'stock-label--empty';
                        $labelText = 'Rupture de stock';
                    } elseif ($isStockLimit) {
                        $labelClass = 'stock-label--empty';
                        $labelText = 'Stock maximum atteint';
                    } elseif ($isMaxLimit) {
                        $labelClass = 'stock-label--low';
                        $labelText = 'Quantité max atteinte';
                    } elseif ($realStock <= 3) {
                        $labelClass = 'stock-label--low';
                        $labelText = 'Plus que ' . $realStock;
                    }
                @endphp
                
                <div class="product-card">
                    <div class="product-card-top">
                        <div>
                            <p class="product-card-name">{{ $product->name }}</p>
                            <span class="stock-label {{ $labelClass }}">{{ $labelText }}</span>
                            @if ($qty > 0 && !$isUnavailable && !$isStockLimit)
                                <div class="muted small">{{ $theoreticalStock }} restant (Stock réel : {{ $realStock }})</div>
                            @endif
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

{{-- CATÉGORIES --}}
@foreach ($categories as $category)

<section class="panel" style="margin: 14px;">
    <details class="collapsible" data-category-id="{{ $category->id }}">
        <summary class="collapsible-summary">
            <span class="accordion-title">{{ $category->name }}</span>
        </summary>

        <div class="accordion-body">

            @forelse ($category->products as $product)
            @php
                $qty = $cart[$product->id] ?? 0;
                $realStock = (int) $product->stock;
                $theoreticalStock = max(0, $realStock - $qty);
                $maxQty = 20;
                $isUnavailable = !$product->is_available;
                $isStockLimit = $qty >= $realStock;
                $isMaxLimit = $qty >= $maxQty;
                $disablePlus = $isUnavailable || $isStockLimit || $isMaxLimit;

                $labelClass = 'stock-label--ok';
                $labelText = $realStock . ' en stock';
                
                if ($isUnavailable) {
                    $labelClass = 'stock-label--unavailable';
                    $labelText = 'Indisponible';
                } elseif ($realStock === 0) {
                    $labelClass = 'stock-label--empty';
                    $labelText = 'Rupture de stock';
                } elseif ($isStockLimit) {
                    $labelClass = 'stock-label--empty';
                    $labelText = 'Stock maximum atteint';
                } elseif ($isMaxLimit) {
                    $labelClass = 'stock-label--low';
                    $labelText = 'Quantité max atteinte';
                } elseif ($realStock <= 3) {
                    $labelClass = 'stock-label--low';
                    $labelText = 'Plus que ' . $realStock;
                }
            @endphp
                <div class="product-row {{ $isUnavailable ? 'product-row--unavailable' : '' }}">

                    <div class="product-info">
                        <span class="product-name">{{ $product->name }}</span>
                        <span class="product-price">{{ euros($product->sale_price) }}</span>
                        <span class="stock-label {{ $labelClass }}">{{ $labelText }}</span>
                        @if ($qty > 0 && !$isUnavailable && !$isStockLimit)
                            <div class="muted small">{{ $theoreticalStock }} restant (Stock réel : {{ $realStock }})</div>
                        @endif
                    </div>

                    <div class="product-controls">

                        <form method="POST" action="{{ route('bar.cart.remove') }}">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <button type="submit" class="btn-qty {{ $qty === 0 ? 'btn-qty--disabled' : '' }}" {{ $qty === 0 ? 'disabled' : '' }}>
                            −
                        </button>

                        </form>

                        <span class="qty-val">{{ $qty }}</span>

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
