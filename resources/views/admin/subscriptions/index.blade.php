<h1>Subscriptions</h1>
<a href="{{ route('admin.subscriptions.create') }}">New subscription</a>
<ul>
@foreach($subscriptions as $subscription)
  <li>
    <a href="{{ route('admin.subscriptions.show',$subscription) }}">{{ $subscription->title }}</a> ({{ $subscription->subscriptionable_type }})
  </li>
@endforeach
</ul>
