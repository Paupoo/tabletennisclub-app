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
                <!-- <form method="POST" action="{{ route('bar.payment.pay', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="offered">
                    <div class="form-field">
                        <label class="form-label">Raison *</label>
                        <input type="text" name="reason" class="form-input" required>
                    </div>
                    <button class="btn btn-clear btn-block">
                        🎁 Offert
                    </button>
                </form> -->
                <button class="btn btn-clear" onclick="toggleOfferedForm()">🎁 Offert</button>
                <form id="offered-form" style="display:none;" method="POST" action="{{ route('bar.payment.pay', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="offered">
                    <input class="product-input" type="text" name="reason" placeholder="Raison..." required>
                    <button class="btn btn-clear" type="submit">Confirmer</button>
                </form>

                {{-- Cash --}}
                <form method="POST" action="{{ route('bar.payment.pay', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="cash">
                    <button class="btn btn-pay">
                        💵 Cash
                    </button>
                </form>
                {{-- QR --}}
                <form method="POST" action="{{ route('bar.payment.show', ['order' => $order->id, 'method' => 'qr']) }}">
                    @csrf
                    <input type="hidden" name="method" value="qr">
                    <button class="btn btn-pay">
                        📱 QR Code
                    </button>
                </form>
            </div>
        </div>
        @if($method === 'qr' && $qrCode)
            <div class="payment-qr">
                <h3>Scannez pour payer</h3>
                <img src="{{ $qrCode }}" alt="QR Code">
                <p>Montant : {{ euros($order->total_price) }}</p>
                {{-- Confirmation button --}}
                <form method="POST" action="{{ route('bar.payment.pay', $order) }}">
                    @csrf
                    <input type="hidden" name="method" value="qr">
                    <button class="btn btn-success">✅ Paiement reçu</button>
                </form>
            </div>
        @endif
    </section>
</div>

@endsection
<script>
function toggleOfferedForm() {
    const el = document.getElementById('offered-form');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>