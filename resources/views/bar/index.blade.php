@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>🍹 Bar - Commande</h1>
    <p class="muted">{{ __('Select the products to add to the order.') }}</p>
</div>
@if($cartCount > 0)
    <div class="cart-bar" style="margin:14px">
        <span>
          <strong>{{ $cartCount }}</strong> article(s)
        </span>
        <a href="{{ route('bar.cart.show') }}" class="btn-cart">🛒 Voir la commande</a>
      </div>

<!-- <div class="cart-summary-bar">

    <div class="cart-summary-left">
        🛒 <strong>{{ $cartCount }}</strong> article(s)
    </div>

    <div class="cart-summary-middle">
        💰 {{ euros($totalPrice) }}
    </div>

    <div class="cart-summary-right">
         <a href="{{ route('bar.cart.show') }}" class="btn-cart">🛒 Voir la commande</a>
    </div>

</div> -->

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
                <p class="muted">{{ __('No products in this category.') }}</p>
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
