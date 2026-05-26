@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>📜 Historique des commandes</h1>
</div>

{{-- FILTERS --}}

<div class="panel" style="margin:14px;">

    {{-- PERIOD --}}
    <div>
        <strong>Période :</strong>

        @foreach ($periodLabels as $k => $label)
            <a href="{{ route('bar.orders.history', ['period' => $k, 'status' => $status ?? 'all']) }}"
               class="chip {{ (string)$k === (string)($period ?? 'today') ? 'chip--active' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- STATUS --}}
    <div style="margin-top:10px;">
        <strong>Statut :</strong>

        @foreach ($statusLabels as $k => $label)
            <a href="{{ route('bar.orders.history', ['period' => $period ?? '7', 'status' => $k]) }}"
               class="chip {{ $k === ($status ?? 'all') ? 'chip--active' : '' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

</div>
<div class="kpis">
    <div class="kpi-card">
        <div class="kpi-label">📦 Commandes :</div>
        <div class="kpi-value">{{ $orderCount }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">💰 Payés :</div>
        <div class="kpi-value">{{ euros($totalRevenue) }}</div>
    </div>
        <div class="kpi-card">
            <div class="kpi-label">💰 Non payés :</div>
            <div class="kpi-value">{{ euros($totalRevenueUnpaid) }}</div>
        </div>
    </div>
</div>
{{-- ORDERS LIST --}}
<section class="panel" style="margin: 14px;">

    <div class="table-title">Historique des commandes</div>

    @if(empty($orders) || $orders->isEmpty())
        <p class="muted">Aucune commande pour les filtres sélectionnés.</p>
    @else
    <div class="table-wrap">
        <table class="hist-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Articles</th>
                    <th>Total</th>
                    <th>Payé</th>
                </tr>
            </thead>

            <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td class="col-id">#{{ $order->id }}
                            <p class="muted" style="font-size:0.9em;">
                                {{ $order->created_at->format('d/m/Y') }}
                            </p>
                        </td>

                        <td class="col-items">
                            @if($order->items->isEmpty())
                                (Aucun article)
                            @else
                                    @foreach($order->items as $item)
                                        <div class="itemline">
                                            {{ $item->product->name }}
                                            x {{ $item->quantity }}
                                        </div>
                                    @endforeach
                            @endif
                        </td>

                        <td class="col-total"><b>{{ euros($order->total_price) }}</b></td>

                        <td class="col-flag">
                            @if($order->is_paid)
                                <span class="flag flag--ok">💳 Payé</span>
                            @else
                                <span class="flag flag--warn">❌ Non payé</span>
                            @endif
                        </td>

                    </tr>
                @endforeach
            </tbody>
            <tfoot style="background-color: black;">
                <tr>
                    <td colspan="2"><strong>Total</strong></td>
                    <td><strong>{{ euros($totalRevenue + $totalRevenueUnpaid) }}</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        @endif
    </div>
</section>

@endsection