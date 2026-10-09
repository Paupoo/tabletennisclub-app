<x-mail::message>
@php
    $registration = $payment->payable;
    $camp = $registration->registrable;
@endphp
# Inscription confirmée

Bonjour **{{ $registration->greetingName() }}**,

L'inscription de **{{ $registration->first_name }} {{ $registration->last_name }}** au stage **{{ $camp->name }}** ({{ $camp->pack_start_date->format('d/m/Y') }} – {{ $camp->pack_end_date->format('d/m/Y') }}) est confirmée.

Montant à régler : **{{ number_format($payment->balance(), 2, ',', ' ') }} €**

<x-mail::panel>
**Coordonnées bancaires**

- Bénéficiaire : {{ $beneficiary }}
- IBAN : {{ $IBAN }}
- BIC : {{ $BIC }}
- Communication : **{{ $payment->reference }}**
</x-mail::panel>

{{ $instructions }}

**QR code de paiement :**

{{-- Pièce jointe nommée, comme l'invitation au paiement : Gmail retire une « data: » URI. --}}
<img src="cid:qr-paiement.png" alt="{{ __('Payment QR code') }}" style="max-width: 160px; display: block;" />

Au plaisir de vous accueillir au club !
</x-mail::message>
