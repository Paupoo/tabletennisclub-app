<div>
    <p>Bonjour {{ $payment->payable->user->first_name ?? '' }},</p>

    <p>Votre inscription au tournoi <strong>{{ $tournament->name }}</strong>
        @if($tournament->start_date)
            du <strong>{{ $tournament->start_date->format('d/m/Y') }}</strong>
        @endif
        a bien été enregistrée.</p>

    <p>Pour finaliser votre inscription, veuillez effectuer le virement de
        <strong>{{ number_format($payment->amount_due, 2, ',', ' ') }} €</strong>
        avant le <strong>{{ $deadline->format('d/m/Y') }}</strong>.</p>

    <p><strong>Coordonnées bancaires :</strong></p>
    <ul>
        <li>Bénéficiaire : {{ $beneficiary }}</li>
        <li>IBAN : {{ $IBAN }}</li>
        <li>BIC : {{ $BIC }}</li>
        <li>Communication structurée : <strong>{{ $payment->reference }}</strong></li>
    </ul>

    <p><strong>QR code de paiement :</strong></p>
    <img src="{{ $qrCode }}" alt="QR code de paiement" style="max-width:200px;" />

    <p style="color:#b45309;">Sans paiement dans les 72h, votre inscription sera annulée et la place proposée au suivant sur la liste d'attente.</p>

    <p>Merci pour votre inscription et à bientôt sur les tables !</p>
</div>
