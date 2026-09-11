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

{{-- Référencée par son nom : Symfony retrouve la pièce jointe qui le porte,
     réécrit le cid et la bascule en inline. Une « data: » URI serait plus
     courte, et Gmail la retirerait — le message n'a longtemps montré que
     son texte alternatif en production. --}}
<img src="cid:qr-paiement.png" alt="{{ __('Payment QR code') }}" style="max-width: 160px; display: block;" />

*{{ __('If you have already paid by the time you receive this message, please ignore this reminder.') }}*

Merci pour votre inscription et votre engagement dans notre club !
</x-mail::message>
