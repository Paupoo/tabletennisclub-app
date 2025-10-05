<h1>Subscriptions</h1>
<a href="{{ route('subscriptions.create') }}">New subscription</a>
<ul>
@foreach($subscriptions as $subscription)
  <li>
    <a href="{{ route('subscriptions.show',$subscription) }}">{{ $subscription->title }}</a> ({{ $subscription->subscriptionable_type }})
  </li>
@endforeach
</ul>
