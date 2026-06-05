<x-mail::message>
# Invitation au paiement

Bonjour **{{ $payment->payable->user->first_name ?? '' }}**,

@if($payment->payable instanceof \App\Domains\ClubAdmin\Subscriptions\Models\Subscription)
Vous êtes inscrit à la saison **{{ $payment->payable->season?->name ?? '' }}**.
@elseif($payment->payable instanceof \App\Models\ClubEvents\Tournament\TournamentRegistration)
Vous êtes inscrit au tournoi **{{ $payment->payable->tournament?->name ?? '' }}**.
@endif

Montant à régler : **{{ number_format($payment->amount_due, 2, ',', ' ') }} €**

<x-mail::panel>
**Coordonnées bancaires**

- Bénéficiaire : {{ $beneficiary }}
- IBAN : {{ $IBAN }}
- BIC : {{ $BIC }}
- Communication : **{{ $payment->reference }}**
</x-mail::panel>

{{ $instructions }}

**QR code de paiement :**

<img src="{{ $qrCode }}" alt="QR code de paiement" style="max-width: 160px; display: block;" />

Merci pour votre inscription et votre engagement dans notre club !
</x-mail::message>
