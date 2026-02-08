<h1>Subscriptions</h1>
<a href="{{ route('clubAdmin.subscriptions.create') }}">New subscription</a>
<ul>
  @foreach($subscriptions as $subscription)
  <li>
    <a href="{{ route('clubAdmin.subscriptions.show',$subscription) }}">{{ $subscription->title }}</a> ({{ $subscription->subscriptionable_type }})
  </li>
  @endforeach
</ul>