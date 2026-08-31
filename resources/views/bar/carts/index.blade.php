@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>🛒 Ma commande</h1>
    <p class="muted">Vérifiez les articles dans votre panier avant de valider votre commande.</p>
</div>
<!-- <a class="pos-link" href="{{ route('bar.index') }}" style="white-space:nowrap;">← Retour au menu</a> -->
<div class="wrap">
    <section class="panel">
        {{-- Content --}}
        @if($items->isEmpty())
        <p>Votre panier est vide.</p>
        <a class="btn btn-pay btn-block" href="{{ route('bar.index') }}">Retour au menu</a>

        @else

            @foreach($items as $item)
                @php
                    $product = $item['product'];
                    $qty = $item['quantity'];
                    $lineTotal = $item['total_price'];
                    $unitPrice = $product->sale_price;
                    $realStock = (int) $product->stock;
                    $availableStock = max(0, $realStock);
                    $isStockLimit = $qty >= $realStock;
                    $disablePlus = $isStockLimit;
                @endphp

                <div class="order-line">

                    {{-- LEFT --}}
                    <div>
                        <div class="order-item-name">{{ $product->name }}</div>
                        <div class="muted" style="margin-top:4px; font-weight:900;">
                            {{ euros($unitPrice) }} / unité • Stock dispo : {{ $availableStock }}
                        </div>
                        @if ($isStockLimit)
                            <div class="text-warning small" style="display: flex;">Stock maximum atteint pour ce produit.</div>
                        @endif
                    </div>

                    {{-- RIGHT --}}
                    <div class="order-item-right">
                        <div class="qty">

                            {{-- MINUS --}}
                            <form method="POST" action="{{ route('bar.cart.remove') }}" style="margin:0;">
                                @csrf
                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <button class="btn-qty" type="submit" {{ $qty === 0 ? 'disabled' : '' }}>
                                    −
                                </button>
                            </form>

                            <span class="qty-badge">{{ $qty }}</span>

                            {{-- PLUS --}}
                            <form method="POST" action="{{ route('bar.cart.add') }}" style="margin:0;">
                                @csrf
                                @php
                                    $disablePlus = $qty >= $realStock || $qty >= 20;
                                @endphp

                                <input type="hidden" name="product_id" value="{{ $product->id }}">
                                <button class="btn-qty" type="submit" {{ $disablePlus ? 'disabled' : '' }}>
                                    +
                                </button>
                            </form>

                        </div>
                    </div>

                    {{-- TOTAL LINE --}}
                    <div class="line-total">
                        {{ euros($lineTotal) }}
                    </div>

                </div>

            @endforeach

            <div class="total">
                <span>Total : </span>
                <span>{{ euros($totalPrice) }}</span>
            </div>
            <div class="cta">
                <!-- FIRST ROW (2 buttons side by side) -->
                 <div class="cta-row">
                    <form method="POST" action="{{ route('bar.cart.clear') }}" class="cta-form">
                        @csrf
                        <button class="btn btn-clear">🗑 Vider le panier</button>
                    </form>
                    <form method="POST" action="{{ route('bar.cart.validate') }}" class="cta-form">
                        @csrf
                        <button class="btn btn-pay" name="action" value="validate">
                            ✅ Valider la commande
                        </button>
                    </form>
                </div>
                <!-- SECOND ROW (full width button) -->
                <form method="POST" action="{{ route('bar.cart.validate') }}">
                    @csrf
                    <button class="btn btn-pay" type="submit" name="action" value="pay_now">
                        💳 Payer maintenant
                    </button>
                </form>
            </div>
        @endif
    </section>
</div>

@endsection