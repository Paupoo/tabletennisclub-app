<x-mail::message>
# {{ __('A fine has been cancelled') }}

{{ __('Hello :name,', ['name' => $member->first_name]) }}

{{ __('Good news: the committee has cancelled a fine that concerned you. You have nothing left to pay for it.') }}

**{{ __('Reason') }}:** {{ $fine->reason->label() }}
@if($fine->event_label || $fine->event_date)
**{{ __('Event') }}:** {{ collect([$fine->event_label, $fine->event_date?->format('d/m/Y')])->filter()->implode(' – ') }}
@endif

{{ __('If you had already paid the provincial committee, ask its treasurer for a refund: the club never received that money.') }}

{{ __('Thanks for your understanding,') }}
{{ __('The committee') }}

@if($creditor->hasContact())
<small style="color: #6b7280;">
{{ __('Provincial committee contact:') }}
{{ collect([$creditor->contactName(), $creditor->contactEmail(), $creditor->contactPhone()])->filter()->implode(' · ') }}
</small>
@endif
</x-mail::message>
