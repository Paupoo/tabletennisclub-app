<h1>Registrations</h1>
<a href="{{ route('registrations.create') }}">New registration</a>
<ul>
@foreach($registrations as $registration)
  <li>
    <a href="{{ route('registrations.show',$registration) }}">{{ $registration->id }}</a>
  </li>
@endforeach
</ul>
