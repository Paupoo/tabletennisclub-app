<x-mail::message>
# {{ __('A fine has been cancelled') }}

{{ __('Hello :name,', ['name' => $member->first_name]) }}

{{ __('Good news: the committee has cancelled a fine that concerned you. You have nothing left to pay for it.') }}

**{{ __('Reason') }}:** {{ $fine->reason->label() }}
@if($fine->federation_reference)
**{{ __('Federation reference') }}:** {{ $fine->federation_reference }}
@endif

{{ __('If you had already arranged the payment, it will not be collected. Please get in touch with the committee if you have any doubt.') }}

{{ __('Thanks for your understanding,') }}
{{ __('The committee') }}
</x-mail::message>
