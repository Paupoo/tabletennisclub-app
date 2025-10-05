<h1>Seasons</h1>
<a href="{{ route('seasons.index') }}">Back to seasons</a>
<hr>
<form action="{{ route ('seasons.store')}}" method="post">
  @csrf
  <label for="name">Name</label>
  <input type="text" name="name" id="name">
  <label for="start_at">Start at</label>
  <input type="date" name="start_at" id="start_at">
  <label for="end_at">End at</label>
  <input type="date" name="end_at" id="end_at">

  <button type="submit">Create</button>
</form>