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
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @foreach ($subscriptions as $subscription)
        <li>{{ $subscription->user->fullName }} -- {{ $subscription->getStatus() }} -- {{ $subscription->is_competitive ? 'Compet' : 'Loisir' }} -- Doit payer : {{ $subscription->amount_due - $subscription->amount_paid }} €
            <form action="{{ route('admin.subscriptions.unsubscribe', parameters: [$season, $subscription->user])}}" method="POST">
                @csrf
                <button class="submit" formaction="{{ route('admin.subscriptions.unconfirm', parameters: $subscription )}}">Set back to pending</button>
                <button class="submit" formaction="{{ route('admin.subscriptions.confirm', parameters: $subscription )}}">Confirm</button>
                <button class="submit" formaction="{{ route('admin.subscriptions.markPaid', parameters: $subscription )}}">Mark as paid</button>
                <button class="submit" formaction="{{ route('admin.subscriptions.markRefunded', parameters: $subscription )}}">Mark as refunded</button>
                <button class="submit" formaction="{{ route('admin.subscriptions.cancel', parameters: $subscription )}}">Cancel</button>
                <button class="submit" formaction="{{ route('admin.subscriptions.delete', parameters: $subscription )}}">Unsubscribe (soft delete)</button>
                <button class="submit">Unsubscribe (destroy)</button>
                @if ($subscription->payments->count() <= 0)
                    <button class="submit" formaction="{{ route('admin.subscription.generatePayment', parameters: $subscription )}}">Generate a payment</button>
                @endif
            </form>

            <form action="" method="POST">
                @csrf
            </form>

            <form action="" method="POST">
                @csrf
                @if ($subscription->payments->count() > 0)
                    <select name="payment_id" id="payment_id" required>
                        <option value="" disabled selected>Select a payment</option>
                        @foreach ($subscription->payments as $payment)
                            <option value="{{ $payment->id }}">{{ $payment->reference }}</option>
                        @endforeach
                    </select>
                    <button class="submit">Validate payment</button>
                    <button class="submit" formaction="{{ route('admin.subscriptions.sendPaymentInvite') }}">Send payment invite</button>
                @endif
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