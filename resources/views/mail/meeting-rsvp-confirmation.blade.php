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
- {{ __('Beneficiary') }}: CTT Ottignies-Blocry ASBL
- IBAN: BE23 7323 3320 8791
- BIC: CREGBEBB
- {{ __('Reference') }}: **{{ $payment->reference }}**
</x-mail::panel>

**{{ __('QR code for payment') }}:**

<img src="{{ $qrCode }}" alt="QR code de paiement" style="max-width: 160px; display: block;" />
@endif

{{ __('See you there!') }}
</x-mail::message>
