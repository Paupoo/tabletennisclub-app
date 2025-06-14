<x-mail::message>
# Too bad, {{ $user->first_name }} !

We have recorded your unregistration from {{ $tournament->name }}.
We are sorry to learn that you won't join anymore.

## Changed your mind?
If the registration are not closed yet, you can still register again by clicking on the button below.
<x-mail::button :url="'http://localhost:8000/admin/tournament/' . $tournament->id . '/register/' . $user->id">
Register back
</x-mail::button>

See you next time! <br>


The Committee,<br>
The club.{{ config('app.name') }}
</x-mail::message>
