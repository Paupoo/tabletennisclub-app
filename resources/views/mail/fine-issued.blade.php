<x-mail::message>
# {{ __('A fine has been issued') }}

{{ __('Hello :name,', ['name' => $member->first_name]) }}

{{ __('The provincial committee has fined you. The club passes the information on to you, but collects nothing: you pay the committee directly.') }}

**{{ __('Reason') }}:** {{ $fine->reason->label() }}{{ $fine->provincial_code ? ' (' . __('code :code', ['code' => $fine->provincial_code]) . ')' : '' }}

@if($fine->event_label || $fine->event_date)
**{{ __('Event') }}:** {{ collect([$fine->event_label, $fine->event_date?->format('d/m/Y')])->filter()->implode(' – ') }}
@endif

**{{ __('Amount due') }}:** {{ number_format($fine->amount, 2, ',', ' ') }} €

---

{{-- The committee's personalised, educational message --}}
{{ $fine->pedagogical_message }}

@if($payable)
---

<x-mail::panel>
**{{ __('To be paid by :date at the latest', ['date' => $fine->payment_deadline->format('d/m/Y')]) }}**

{{ __('Past that date, you lose your qualification: you can no longer play any competition, individual or in a team, until the committee has received the payment.') }}
</x-mail::panel>

**{{ __('How to pay') }}**

- {{ __('Beneficiary') }}: {{ $creditor->name() }}
- IBAN: {{ $creditor->ibanFormatted() }}
- {{ __('Amount') }}: {{ number_format($fine->amount, 2, ',', ' ') }} €
- {{ __('Communication') }}: **{{ $fine->transferCommunication() }}**

{{-- Référencée par son nom : Symfony retrouve la pièce jointe qui le porte,
     réécrit le cid et la bascule en inline. Une « data: » URI serait plus
     courte, et Gmail la retirerait. --}}
<img src="cid:qr-paiement.png" alt="{{ __('Payment QR code') }}" style="max-width: 160px; display: block;" />

{{ __('Paying on time is your sole responsibility: the club is not told whether you paid, and will not remind you.') }}
@endif

<x-mail::button :url="route('admin.user.profile', $member->id)">
{{ __('View my fines') }}
</x-mail::button>

{{ __('Thanks for your understanding,') }}
{{ __('The committee') }}

@if($creditor->hasContact())
<small style="color: #6b7280;">
{{ __('A question about this fine? Only the provincial committee can answer it:') }}
{{ collect([$creditor->contactName(), $creditor->contactEmail(), $creditor->contactPhone()])->filter()->implode(' · ') }}
</small>
@endif
</x-mail::message>
