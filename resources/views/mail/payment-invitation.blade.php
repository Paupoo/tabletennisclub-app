<div>
    <p>Bonjour {{ $payment->payable->user->first_name }},</p>

    <p>Vous êtes inscrit à la saison {{ $payment->payable->season->name }}.</p>

    <p>Montant à payer : {{ $payment->amount_due }} €</p>
    <p>Référence pour le paiement :</br> 
        <ul>
            <li>IBAN = {{ $IBAN }}</li>
            <li>BIQ = {{ $BIC }}</li>
            <li>Bénéficiaire : {{ $beneficiary }}</li>
            <li>Communication : {{ $payment->reference }}</li>
        </ul>
    </p>
    <p>{{ $instructions }}</p>

    <p><strong>Votre QR code pour le paiement :</strong></p>
    <img src="{{ $qrCode }}" alt="QR code de paiement" />

    <p>Merci pour votre inscription et votre engagement dans notre club!</p>

</div>
