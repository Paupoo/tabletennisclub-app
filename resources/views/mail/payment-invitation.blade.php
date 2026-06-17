<x-mail::message>
# Invitation au paiement

Bonjour **{{ $payment->payable->user->first_name ?? '' }}**,

@php $label = $payment->payable->getPaymentLabel(); @endphp
Vous avez un paiement en attente pour **{{ $label['type'] }}** : **{{ $label['name'] }}**.

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

*{{ __('If you have already paid by the time you receive this message, please ignore this reminder.') }}*

Merci pour votre inscription et votre engagement dans notre club !
</x-mail::message>
