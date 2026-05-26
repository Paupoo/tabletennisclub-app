@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>📋 Commandes</h1>
    <p class="muted">Liste des commandes en cours et passées.</p>
</div>
        {{-- No orders --}}
        @if($orders->isEmpty())
            <p>Aucune commande pour l'instant.</p>
        @else

            @foreach($orders as $order)

                <div class="orders-cards">
                    <article class="order-card">
                        <div class="order-card-bar"></div>
                        <div class="order-card-header">
                            <div class="order-card-title">Commande #{{ $order->id }}</div>
                            <div class="order-card-time">{{ $order->created_at->format('d/m/Y H:i') }}</div>
                        </div>
                        <div class="order-card-items">
                            <div class="order-item-line">
                                @foreach($order->items as $item)
                                    <div class="order-item">
                                        <span class="order-item-qty">{{ $item->quantity }} x</span>
                                        <span class="order-item-name">{{ $item->product->name }}</span>
                                    </div>
                                @endforeach
                                
                            </div>
                        </div>
                         <div class="order-card-badges">
                            @if($order->is_paid)
                                <span class="chip chip-ok">✅ Payé</span>
                            @else
                                <span class="chip chip-warn">❌ Non payé</span>
                            @endif

                            <!-- @if($order->is_closed)
                                <span class="chip chip-closed">🔒 Fermé</span>
                            @else
                                <span class="chip chip-open">🔓 Ouvert</span>
                            @endif -->
                            <span class="chip chip--muted">💳 Total: {{ euros($order->total_price) }}</span>
                         </div>
                         <div class="order-card-actions">
                            <a href="{{ route('bar.payment.show', $order) }}" class="btn btn-pay btn-block"">💰 Payer</a>
                         </div>
                    </article>
                </div>

            @endforeach

        @endif
</div>

@endsection
