@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>🧾 Feuille de caisse</h1>
</div>
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if(session('warning'))
    <div class="alert alert-warning">
        {{ session('warning') }}
    </div>
@endif
<section class="panel" style="margin:14px;">

    <form method="GET" action="{{ route('bar.cashSheet.index') }}">
        <div class="cashsheet__formRow">
            <div class="form-field">
                <label class="panel-title">Date :</label>
                <input class="form-input" type="date" name="date" value="{{ $date ?? now()->toDateString() }}" required />
            </div>
            <button class="btn btn-pay" type="submit">Afficher</button>
        </div>
    </form>
</section>

<div class="cashsheet__kpis">
    <div class="cashsheet__kpi">
        <div class="panel-title">Commandes</div>
        <div class="cashsheet__kpiValue">{{ $summary['orders_total'] }}</div>
        <div class="muted">
            Payées : <strong>{{ $summary['orders_paid'] }}</strong> •
            Non payées : <strong>{{ $summary['orders_unpaid'] }}</strong>
        </div>
    </div>
    <div class="cashsheet__kpi">
        <div class="panel-title">Articles vendus</div>
        <div class="cashsheet__kpiValue">{{ $summary['items_total'] }}</div>
    </div>
    <div class="cashsheet__kpi">
        <div class="panel-title">Total vendu</div>
        <div class="cashsheet__kpiValue">{{ euros($summary['sold_total_cents']) }}</div>
    </div>
    <div class="cashsheet__kpi">
        <div class="panel-title">Total encaissé</div>
        <div class="cashsheet__kpiValue">{{ euros($summary['received_total_cents']) }}</div>
    </div>
</div>

<section class="panel" style="margin:14px;">
    <div class="panel-title">Sous-totaux par méthode</div>
        <div class="cashsheet__methodGrid">
            <div class="chip cashsheet__chip">
                <span>Cash : </span>
                <p>{{ euros($summary['by_method_cents']['cash'] ?? 0) }}</p>
            </div>
            <div class="chip cashsheet__chip">
                <span>QR : </span>
                <p>{{ euros($summary['by_method_cents']['qr'] ?? 0) }}</p>
            </div>
            <div class="chip cashsheet__chip">
                <span>Offert : </span>
                <p>{{ euros($summary['by_method_cents']['offered'] ?? 0) }}</p>
            </div>
            <div class="chip cashsheet__chip">
                <span>Autre : </span>
                <p>{{ euros($summary['by_method_cents']['other'] ?? 0) }}</p>
            </div>
        </div>
        <div class="muted cashsheet__unpaid"><strong>Impayé :</strong> {{ euros($summary['unpaid_total_cents']) }}</div>
</section>

<section class="panel" style="margin:14px;">
    <div class="panel-title">Envoyer au trésorier</div>

    <form class="cashsheet__send" method="POST" action="{{ route('bar.cashSheet.send') }}">
        @csrf
        <div class="form-field">
            <label class="form-label">Adresse Email *</label>
            <input class="form-input" type="email" name="to" placeholder="cttottigniesblocry@gmail.com" required />
        </div>
        <label class="form-check">
            <input type="checkbox" name="save_default" value="1">
            <span>Enregistrer comme défaut</span>
        </label>
        <button class="btn btn-pay btn-block" type="submit">📧 Envoyer</button>
        </div>
    </form>

    <p class="muted" style="margin-top:10px;">
        Note : l'envoi dépend de la configuration mail du serveur. Sinon, exportez en CSV et envoyez manuellement.
    </p>
</section>
@endsection
