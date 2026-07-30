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

<img src="{{ $qrCode }}" alt="QR code de paiement" style="max-width: 160px; display: block;" />

*Sans paiement au terme de ce délai, votre inscription sera annulée et la place proposée au suivant sur la liste d'attente.*

*{{ __('If you have already paid by the time you receive this message, please ignore this reminder.') }}*

À bientôt sur les tables !
</x-mail::message>
