<x-mail::message>
{{ __('Hello :name,', ['name' => $guardian->full_name]) }}

@if ($wards->count() === 1)
{{ __(':name is a member of **:app**, and has no email address of their own with us.', ['name' => $wards->first()->first_name, 'app' => config('app.name')]) }}
@else
{{ __('You are on file at **:app** as the person answering for :names.', ['app' => config('app.name'), 'names' => $wards->pluck('first_name')->join(', ', ' ' . __('and') . ' ')]) }}
@endif

{{ __('So that you can look after their membership yourself, we are inviting you to create your own account. Your login will be :email.', ['email' => $guardian->email]) }}

<x-mail::button :url="$link" :color="'primary'">
{{ __('Create my account') }}
</x-mail::button>

{{ __('Once logged in, you will be able to:') }}

- {{ __('Complete and renew the membership') }}
- {{ __('Choose the training sessions') }}
- {{ __('Register for tournaments and follow the results') }}
- {{ __('Keep an eye on what is left to pay') }}

@if ($wards->count() > 1)
{{ __('A single account gives you access to all of them; you switch from one to the other from the menu.') }}
@endif

{{ __('This link is valid for :days days.', ['days' => \App\Domains\ClubAdmin\Users\Models\User::INVITATION_LINK_VALIDITY_DAYS]) }}

{{ __('See you soon at the club!') }}

{{ __('Sportingly,') }}
**{{ __('The committee of :app', ['app' => config('app.name')]) }}**

<small>{{ __('This email was sent automatically, please do not reply.') }}</small>
</x-mail::message>
