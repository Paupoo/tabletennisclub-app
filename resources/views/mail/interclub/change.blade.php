<x-mail::message>
# {{ $headline }}

{{ __('Hello :name!', ['name' => $notifiable->first_name]) }}

{{ $lead }}

@foreach ($rows as $row)
<x-mail::panel>
@if ($isReschedule)
**{{ __('Before') }} :** {{ $row['before'] }}

**{{ __('Now') }} :** {{ $row['after'] }}
@else
{{ $row['after'] }}
@endif
</x-mail::panel>
@endforeach

@if ($forCaptain)
**{{ trans_choice('{0} Nobody else in your team was told.|{1} Your team has been told: 1 member received this message.|[2,*] Your team has been told: :count members received this message.', $informedCount, ['count' => $informedCount]) }}**
@else
**{{ __('Contact your captain as soon as possible if you have a question or a problem.') }}**
@if ($captain)

{{ $captain->full_name }}@if ($captain->phone_number) · {{ $captain->phone_number }}@endif @if ($captain->email) · {{ $captain->email }}@endif
@endif
@endif

<x-mail::button :url="$url">
{{ __('See the match') }}
</x-mail::button>
</x-mail::message>
