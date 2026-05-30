@extends('bar.layout')

@section('content')

<div class="page-header">
    <h1>🧾 Feuille de caisse</h1>
</div>

<div class="panel" style="margin:14px;">

    <form method="GET" action="{{ route('bar.cashSheet.index') }}">
        <div style="display:flex; gap:10px; align-items:center;">
            <label><strong>Jour :</strong></label>
            <input type="date" name="date" value="{{ $date ?? now()->toDateString() }}" />
            <button class="btn" type="submit">Afficher</button>
        </div>
    </form>

</div>

<div class="panel" style="margin:14px;">

    <h3>Commandes</h3>
    <p>
        Payées : <strong>{{ $paidCount ?? 0 }}</strong> •
        Non payées : <strong>{{ $unpaidCount ?? 0 }}</strong>
    </p>

    <h3>Articles vendus</h3>
    <p><strong>{{ $itemsSold ?? 0 }}</strong></p>

    <h3>Total vendu</h3>
    <p><strong>{{ euros($totalSold ?? 0) }}</strong></p>

    <h3>Total encaissé</h3>
    <p><strong>{{ euros($totalPaid ?? 0) }}</strong></p>

</div>

<div class="panel" style="margin:14px;">

    <h3>Sous-totaux par méthode</h3>

    <ul>
        <li>Cash : {{ euros($totalCash ?? 0) }}</li>
        <li>QR : {{ euros($totalQr ?? 0) }}</li>
        <li>Autre : {{ euros($totalOther ?? 0) }}</li>
        <li>Offert (valeur) : {{ euros($totalFree ?? 0) }}</li>
    </ul>

    <p><strong>Impayé :</strong> {{ euros($totalUnpaid ?? 0) }}</p>

</div>

<div class="panel" style="margin:14px;">

    <h3>Envoyer au trésorier</h3>

    <form method="POST" action="{{ route('bar.cashSheet.send') }}">
        @csrf

        <div style="display:flex; gap:10px; align-items:center;">
            <input type="email" name="email" placeholder="Email" required />

            <label>
                <input type="checkbox" name="save_default" value="1">
                Enregistrer comme défaut
            </label>

            <button class="btn btn-pay" type="submit">📧 Envoyer</button>
        </div>
    </form>

    <p class="muted" style="margin-top:10px;">
        Note : l'envoi dépend de la configuration mail du serveur. Sinon, exportez en CSV et envoyez manuellement.
    </p>

</div>

@endsection
