<h1>Season {{ $season->name }}</h1>
<a href="{{ route('admin.seasons.index') }}">See All Seasons</a>
<ul>
  <li>Name : {{ $season->name }}</li>
  <li>Start at : {{ $season->start_at->format('d/m/Y')   }}</li>
  <li>End at : {{ $season->end_at->format('d/m/Y')   }}</li>
  <li>Total subscriptions {{ $season->users()->count() }}</li>
</ul>

<hr>

<h2>Subscribed users</h2>
<ul>
    @foreach ($subscriptions as $subscription)
        <li>{{ $subscription->user->fullName }} -- {{ $subscription->is_competitive ? 'Compet' : 'Loisir' }} -- Doit payer : {{ $subscription->amount_due - $subscription->amount_paid }} €
            <form action="{{ route('admin.seasons.unsubscribe', parameters: [$season, $subscription->user])}}" method="POST">
                @csrf
                <button class="submit">Unsubscribe (destroy)</button>
            </form>
            <form action="{{ route('admin.subscriptions.cancel', parameters: $subscription )}}" method="POST">
                @csrf
                <button class="submit">Unsubscribe (soft delete)</button>
            </form>
            <form action="" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $subscription->user->id }}">
                <select name="payment_id" id="payment_id">
                    <option value="" disabled selected>Select a payment</option>
                    @foreach ($payments as $payment)
                        <option value="{{ $payment->payment_id}}">{{ $payment->reference }}</option>
                    @endforeach
                </select>
                <button class="submit">Validate payment</button>
            </form>
        </li>
    @endforeach
</ul>

<hr>

<form action="{{ route('admin.seasons.subscribe', $season)}}" method="post">
    @csrf
    <input type="hidden" name="season_id" value="{{ $season->id }}">
    <select name="user_id" id="user_id">
        @foreach($notSubscribedUsers as $notSubscribedUser)
            <option value="{{ $notSubscribedUser->id }}">{{ $notSubscribedUser->FullName }}</option>        
        @endforeach
    </select>
    <label for="casual">Casual</label>
    <input type="radio" name="type" id="casual" value="casual" checked>
    <label for="competitive">Competitive</label>
    <input type="radio" name="type" id="competitive" value="competitive">
    <button type="submit">Subcribe</button>
</form>