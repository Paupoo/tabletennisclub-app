<x-mail::message>
{{ __('Hello :name,', ['name' => $user->first_name . ' ' . $user->last_name]) }}

{{ __('We are delighted to welcome you to **:app**!', ['app' => config('app.name')]) }}

{{ __('Your login is :email.', ['email' => $user->email]) }}

{{ __('To finish creating your account and fill in your personal information, please click the button below:') }}

<x-mail::button :url="$link" :color="'primary'">
{{ __('Finaliser mon inscription') }}
</x-mail::button>

{{ __('Once this step is completed, you will be able to:') }}

- {{ __('Finalize your club membership') }}
- {{ __('Choose your training sessions') }}
- {{ __('Take part in organized events') }}
- {{ __('Easily manage your account online') }}

{{ __('See you soon at the club!') }}

{{ __('Sportingly,') }}
**{{ __('The committee of :app', ['app' => config('app.name')]) }}**

<small>{{ __('This email was sent automatically, please do not reply.') }}</small>
</x-mail::message>
