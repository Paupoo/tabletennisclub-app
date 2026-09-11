<x-mail::message>
# Inscription — {{ $tournament->name }}

Bonjour **{{ $payment->payable->user->first_name ?? '' }}**,

Votre inscription au tournoi **{{ $tournament->name }}**@if($tournament->start_date) du **{{ $tournament->start_date->format('d/m/Y') }}**@endif a bien été enregistrée.

Pour finaliser votre inscription, veuillez effectuer le virement de **{{ number_format($payment->amount_due, 2, ',', ' ') }} €** avant le **{{ $deadline->format('d/m/Y') }}**.

<x-mail::panel>
**Coordonnées bancaires**

- Bénéficiaire : {{ $beneficiary }}
- IBAN : {{ $IBAN }}
- BIC : {{ $BIC }}
- Communication : **{{ $payment->reference }}**
</x-mail::panel>

**QR code de paiement :**

{{-- Référencée par son nom : Symfony retrouve la pièce jointe qui le porte,
     réécrit le cid et la bascule en inline. Une « data: » URI serait plus
     courte, et Gmail la retirerait — le message n'a longtemps montré que
     son texte alternatif en production. --}}
<img src="cid:qr-paiement.png" alt="{{ __('Payment QR code') }}" style="max-width: 160px; display: block;" />

*Sans paiement au terme de ce délai, votre inscription sera annulée et la place proposée au suivant sur la liste d'attente.*

*{{ __('If you have already paid by the time you receive this message, please ignore this reminder.') }}*

À bientôt sur les tables !
</x-mail::message>
