{{-- One report of an export: what was declared, who decided, how it was paid. --}}
<style>
    body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #111827; }
    h1 { font-size: 13pt; color: #154a8a; margin: 0 0 4mm; }
    table.facts { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
    table.facts th { text-align: left; width: 50mm; padding: 1.8mm 0; color: #4b5563; font-weight: normal; vertical-align: top; }
    table.facts td { padding: 1.8mm 0; }
    .proof { margin-top: 4mm; }
    .caption { font-size: 8.5pt; color: #4b5563; margin-bottom: 2mm; }
    .missing { font-size: 9pt; color: #92400e; border: 0.5px solid #f59e0b; padding: 2mm; }
</style>

<h1>{{ __('Expense report #:id', ['id' => $row['id']]) }} — {{ $row['member'] }}</h1>

<table class="facts">
    <tr><th>{{ __('Nature') }}</th><td>{{ $row['category'] }}</td></tr>
    <tr><th>{{ __('Description') }}</th><td>{{ $row['description'] }}</td></tr>
    <tr><th>{{ __('Date of the expense') }}</th><td>{{ $row['spent_on'] }}</td></tr>
    <tr><th>{{ __('Declared on') }}</th><td>{{ $row['declared_on'] }}</td></tr>
    <tr><th>{{ __('Declared amount') }}</th><td>{{ $row['declared_amount'] }} €</td></tr>
    <tr><th>{{ __('Accepted amount') }}</th><td>{{ $row['accepted_amount'] !== '' ? $row['accepted_amount'] . ' €' : '—' }}</td></tr>
    <tr><th>{{ __('Status') }}</th><td>{{ $row['status'] }}</td></tr>
    <tr><th>{{ __('Decided by') }}</th><td>{{ $row['decided_by'] ?: '—' }} {{ $row['decided_at'] ? '· ' . $row['decided_at'] : '' }}</td></tr>
    @if ($row['reason'] !== '')
        <tr><th>{{ __('Reason sent to the member') }}</th><td>{{ $row['reason'] }}</td></tr>
    @endif
    <tr><th>{{ __('Refund reference') }}</th><td>{{ $row['refund_reference'] ?: '—' }}</td></tr>
    <tr><th>{{ __('Paid on') }}</th><td>{{ $row['paid_on'] ?: '—' }}</td></tr>
</table>

@if ($withProofs)
    @foreach ($images as $image)
        <div class="proof">
            <div class="caption">{{ $image['name'] }}</div>
            <img src="{{ $image['src'] }}" style="max-width: 170mm; max-height: 190mm;" />
        </div>
    @endforeach
    @foreach ($unreadable as $name)
        <div class="missing">{{ __('Proof ":name" could not be printed here: see the original in the ZIP export.', ['name' => $name]) }}</div>
    @endforeach
@else
    <p class="caption">{{ trans_choice(':count proof in this report\'s folder|:count proofs in this report\'s folder', $proofCount) }}</p>
@endif
