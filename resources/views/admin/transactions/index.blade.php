<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toutes les transactions</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #2563eb;
            color: white;
            padding: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: white;
            color: #2563eb;
        }
        
        .btn-primary:hover {
            background: #f0f0f0;
        }
        
        .filters {
            padding: 20px 24px;
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .filters input {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #f9fafb;
        }
        
        th {
            text-align: left;
            padding: 12px 24px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }
        
        td {
            padding: 16px 24px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        tr:hover {
            background: #f9fafb;
        }
        
        .amount {
            font-weight: 600;
            font-family: monospace;
        }
        
        .amount.positive {
            color: #059669;
        }
        
        .amount.negative {
            color: #dc2626;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-reconciled {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .empty-state {
            padding: 60px 24px;
            text-align: center;
            color: #6b7280;
        }
        
        .pagination {
            padding: 20px 24px;
            display: flex;
            justify-content: center;
            gap: 8px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            text-decoration: none;
            color: #374151;
        }
        
        .pagination .active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Transactions bancaires</h1>
            <div>
                <a href="{{ route('admin.transactions.reconcile') }}" class="btn btn-primary">
                    Réconcilier les paiements
                </a>
            </div>
        </div>
        
        <div class="filters">
            <form method="GET" action="{{ route('admin.transactions.index') }}">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Rechercher (description, nom, IBAN...)"
                    value="{{ request('search') }}"
                    style="width: 400px;"
                >
                <button type="submit" class="btn btn-primary" style="margin-left: 8px;">Rechercher</button>
            </form>
        </div>
        
        @if($transactions->isEmpty())
            <div class="empty-state">
                <p>Aucune transaction trouvée.</p>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Contrepartie</th>
                        <th>IBAN</th>
                        <th>Montant</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $transaction)
                    <tr>
                        <td style="white-space: nowrap;">
                            {{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}
                        </td>
                        <td>
                            <div style="max-width: 400px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $transaction->description }}
                            </div>
                        </td>
                        <td>{{ $transaction->counterparty_name ?? '-' }}</td>
                        <td style="font-family: monospace; font-size: 13px;">
                            {{ $transaction->counterparty_bank_account ?? '-' }}
                        </td>
                        <td>
                            <span class="amount {{ $transaction->amount >= 0 ? 'positive' : 'negative' }}">
                                {{ number_format($transaction->amount, 2, ',', ' ') }} €
                            </span>
                        </td>
                        <td>
                            @if($transaction->transaction_id)
                                <span class="badge badge-reconciled">Réconcilié</span>
                            @else
                                <span class="badge badge-pending">Non réconcilié</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div class="pagination">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>
</body>
</html>