{{-- One supporting document of an export: what it says, what paid it, its files printed in. --}}
@include('financial-export.styles')

<h1>{{ $document->reference() }} — {{ $document->counterparty }}</h1>

<table class="facts">
    <tr><th>{{ __('Label') }}</th><td>{{ $document->label }}</td></tr>
    <tr><th>{{ __('Date') }}</th><td>{{ $document->date->format('d/m/Y') }}</td></tr>
    <tr><th>{{ __('Category') }}</th><td>{{ $document->isExpense() ? __('Expense') : __('Income') }} — {{ $document->category()->label() }}</td></tr>
    <tr><th>{{ __('Amount') }}</th><td>{{ number_format($document->amount, 2, ',', ' ') }} €</td></tr>
    <tr><th>{{ __('State') }}</th><td>{{ $document->state()->label() }}</td></tr>
    <tr>
        <th>{{ __('Paid by') }}</th>
        <td>
            @forelse ($movements as $movement)
                {{ $movement }}<br>
            @empty
                —
            @endforelse
            @if ($document->hasAmountMismatch())
                <span class="warn">{{ __('The linked movements add up to :amount €', ['amount' => number_format($document->linkedAmount(), 2, ',', ' ')]) }}</span>
            @endif
        </td>
    </tr>
</table>

@foreach ($images as $image)
    <div class="proof">
        <div class="caption">{{ $image['name'] }}</div>
        <img src="{{ $image['src'] }}" style="max-width: 170mm; max-height: 190mm;" />
    </div>
@endforeach
@foreach ($unreadable as $name)
    <div class="missing">{{ __('Proof ":name" could not be printed here: see the original in the ZIP export.', ['name' => $name]) }}</div>
@endforeach
