<div>
    <p>Bonjour {{ $payment->payable->user->first_name ?? '' }},</p>

    @if($payment->payable instanceof \App\Models\ClubAdmin\Subscription\Subscription)
        <p>Vous êtes inscrit à la saison {{ $payment->payable->season?->name ?? '' }}.</p>
    @elseif($payment->payable instanceof \App\Models\ClubEvents\Tournament\TournamentRegistration)
        <p>Vous êtes inscrit au tournoi <strong>{{ $payment->payable->tournament?->name ?? '' }}</strong>.</p>
    @endif

    <p>Montant à payer : <strong>{{ number_format($payment->amount_due, 2, ',', ' ') }} €</strong></p>

    <p>Référence pour le paiement :</p>
    <ul>
        <li>IBAN : {{ $IBAN }}</li>
        <li>BIC : {{ $BIC }}</li>
        <li>Bénéficiaire : {{ $beneficiary }}</li>
        <li>Communication : <strong>{{ $payment->reference }}</strong></li>
    </ul>

    <p>{{ $instructions }}</p>

    <p><strong>Votre QR code pour le paiement :</strong></p>
    <img src="{{ $qrCode }}" alt="QR code de paiement" style="max-width:200px;" />

    <p>Merci pour votre inscription et votre engagement dans notre club !</p>
</div>
