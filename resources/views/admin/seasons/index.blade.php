<h1>Seasons</h1>
<a href="{{ route('seasons.create') }}">New season</a>
<ul>
@foreach($seasons as $season)
  <li>
    <a href="{{ route('seasons.show',$season) }}">{{ $season->name }}</a> ({{ $season->users()->count() }})
  </li>
@endforeach
</ul>
