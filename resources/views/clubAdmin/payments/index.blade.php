<h1>Payments</h1>
<a href="{{ route('admin.payments.create') }}">New payment</a>
<ul>
    @foreach($payments as $payment)
    <li>
        <a href="{{ route('admin.payments.show',$payment) }}">{{ $payment->reference   }}</a> ({{ $payment->status }})
    </li>
    @endforeach
</ul>