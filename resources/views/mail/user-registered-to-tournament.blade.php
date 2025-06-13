<x-mail::message>
# Yeah {{ $user->first_name }} !

Yet another legend is registered to {{ $tournament->name }}, and **this is you** !
Thank you very much for joining us.

## When
{{ $tournament->start_date->format('l jS \of F Y \a\t h:i') }}.

## Where
{{ $tournament->rooms()->first()->street }} <br>
{{ $tournament->rooms()->first()->city_code }} {{ $tournament->rooms()->first()->city_name }}

## More info?
Please visit the tournament page details :

<x-mail::button :url="'http://localhost:8000/admin/tournament/' . $tournament->id">
Get details
</x-mail::button>

We are looking forward to seeing you ! <br>


The Committee,<br>
The club.{{ config('app.name') }}
</x-mail::message>
