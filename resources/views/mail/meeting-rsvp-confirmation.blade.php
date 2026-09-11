<x-mail::message>
# {{ __('Attendance confirmed') }}

{{ __('Thank you, :name!', ['name' => $user->first_name]) }}

{{ __('Your attendance to **:title** is confirmed.', ['title' => $meeting->title]) }}

---

**{{ __('Date') }}:** {{ $meeting->scheduled_at?->translatedFormat('l d M Y') ?? '—' }} {{ __('at') }} {{ $meeting->scheduled_at?->format('H:i') ?? '—' }}

@if($meeting->format === \App\Domains\Shared\Enums\MeetingFormatEnum::PHYSICAL && $meeting->location)
**{{ __('Location') }}:** {{ $meeting->location }}
@elseif($meeting->format === \App\Domains\Shared\Enums\MeetingFormatEnum::VIRTUAL && $meeting->meeting_link)
**{{ __('Meeting link') }}:** [{{ $meeting->meeting_link }}]({{ $meeting->meeting_link }})
@endif

@if($payment)
---

**{{ __('Meal payment') }}**

@if($meeting->meal_description)
{{ $meeting->meal_description }}
@endif

**{{ __('Amount due') }}:** {{ number_format($payment->amount_due, 2, ',', ' ') }} €

<x-mail::panel>
- {{ __('Beneficiary') }}: {{ $club->name }}
- IBAN: {{ $club->bank_account_formatted }}
- BIC: {{ $club->bic }}
- {{ __('Reference') }}: **{{ $payment->reference }}**
</x-mail::panel>

**{{ __('QR code for payment') }}:**

{{-- Référencée par son nom : Symfony retrouve la pièce jointe qui le porte,
     réécrit le cid et la bascule en inline. Une « data: » URI serait plus
     courte, et Gmail la retirerait — le message n'a longtemps montré que
     son texte alternatif en production. --}}
<img src="cid:qr-paiement.png" alt="{{ __('Payment QR code') }}" style="max-width: 160px; display: block;" />
@endif

{{ __('See you there!') }}
</x-mail::message>
