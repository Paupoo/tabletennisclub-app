<x-mail::message>
# {{ __('A fine has been issued') }}

{{ __('Hello :name,', ['name' => $member->first_name]) }}

{{ __('The committee has passed on a fine concerning you. Here is what it is about:') }}

**{{ __('Reason') }}:** {{ $fine->reason->label() }}
@if($fine->federation_reference)
**{{ __('Federation reference') }}:** {{ $fine->federation_reference }}
@endif

---

{{-- The committee's personalised, educational message --}}
{{ $fine->pedagogical_message }}

@if($payment)
---

**{{ __('Amount due') }}:** {{ number_format($payment->amount_due, 2, ',', ' ') }} €

@if($club)
<x-mail::panel>
- {{ __('Beneficiary') }}: {{ $club->name }}
- IBAN: {{ $club->bank_account_formatted }}
- BIC: {{ $club->bic }}
- {{ __('Reference') }}: **{{ $payment->reference }}**
</x-mail::panel>
@endif

@if($payment)
**{{ __('QR code for payment') }}:**

{{-- Référencée par son nom : Symfony retrouve la pièce jointe qui le porte,
     réécrit le cid et la bascule en inline. Une « data: » URI serait plus
     courte, et Gmail la retirerait — le message n'a longtemps montré que
     son texte alternatif en production. --}}
<img src="cid:qr-paiement.png" alt="{{ __('Payment QR code') }}" style="max-width: 160px; display: block;" />
@endif

<x-mail::button :url="route('admin.user.payments', $member->id)">
{{ __('View my payments') }}
</x-mail::button>
@endif

{{ __('Thanks for your understanding,') }}
{{ __('The committee') }}
</x-mail::message>
