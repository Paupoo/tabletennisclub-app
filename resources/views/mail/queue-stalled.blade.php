<x-mail::message>
{{ __('Hello,') }}

{{ __('The oldest pending job has been waiting for :minutes minutes — the queue worker is probably down.', ['minutes' => $oldestMinutes]) }}

- {{ __(':count job(s) waiting in the queue', ['count' => $pendingCount]) }}
- {{ __(':count failed job(s)', ['count' => $failedCount]) }}

{{ __('As long as the worker is down, no email leaves the application (invitations, notifications, reminders).') }}

<x-mail::button :url="route('admin.queue.index')" :color="'primary'">
{{ __('Open the queue monitoring') }}
</x-mail::button>

{{ __('To restart the worker, check the process manager on the server (queue:work).') }}

<small>{{ __('This email was sent automatically, please do not reply.') }}</small>
</x-mail::message>
