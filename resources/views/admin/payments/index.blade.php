<h1>Payments</h1>
<a href="{{ route('payments.create') }}">New payment</a>
<ul>
@foreach($payments as $payment)
  <li>
    <a href="{{ route('payments.show',$payment) }}">{{ $payment->reference   }}</a> ({{ $payment->status }})
  </li>
@endforeach
</ul>
