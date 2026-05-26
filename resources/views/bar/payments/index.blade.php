@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>💳 Bar - Paiement</h1>
    <p class="muted">Finalisez le paiement de la commande.</p>
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <a href="{{ route('bar.orders.index') }}">← Retour aux commandes</a>
    </div>
</div>

<div class="wrap">

    <section class="panel">

        {{-- Header --}}
        

        {{-- Success message --}}
        @if(session('success'))
            <div class="success" style="margin:10px 0;">
                ✅ {{ session('success') }}
            </div>
        @endif

        {{-- Order summary --}}
        <div class="order-summary">
            <p><strong>Commande #{{ $order->id }}</strong></p>
        </div>

        {{-- Items --}}
        <div class="order-items">
            <h4>Articles :</h4>

            @foreach($order->items as $item)
                <div class="order-line">
                    <span>{{ $item->product->name }}</span>
                    <span>{{ euros($item->unit_price) }} / unité</span>
                    <span>x {{ $item->quantity }}</span>
                    <span>{{ euros($item->total_price) }}</span>
                </div>
            @endforeach
        </div>

        {{-- Total --}}
        <div class="total">
            <strong>Total : {{ euros($order->total_price) }}</strong>
        </div>

        {{-- Payment methods --}}
        <div class="payment-methods" style="margin-top:20px;">
            <h4>Mode de paiement</h4>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                {{-- Offered --}}
                <form method="POST" action="{{ route('bar.orders.pay', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="offered">
                    <button class="btn btn-clear btn-block">
                        🎁 Offert
                    </button>
                </form>
                {{-- Cash --}}
                <form method="POST" action="{{ route('bar.orders.pay', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="cash">
                    <button class="btn btn-pay btn-block">
                        💵 Cash
                    </button>
                </form>
                {{-- QR --}}
                <form method="POST" action="{{ route('bar.orders.pay', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="qr">
                    <button class="btn btn-pay btn-block">
                        📱 QR Code
                    </button>
                </form>
            </div>
        </div>
    </section>
</div>

@endsection
