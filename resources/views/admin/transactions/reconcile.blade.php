<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réconciliation des paiements</title>
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
            max-width: 1600px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #6b7280;
            font-size: 14px;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .panel {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .panel-header {
            padding: 16px 20px;
            border-bottom: 2px solid #e5e7eb;
            background: #f9fafb;
        }
        
        .panel-header h2 {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }
        
        .panel-header .count {
            color: #6b7280;
            font-size: 14px;
            font-weight: normal;
        }
        
        .list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .item {
            padding: 16px 20px;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background 0.15s;
        }
        
        .item:hover {
            background: #f9fafb;
        }
        
        .item.selected {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
            padding-left: 16px;
        }
        
        .item-date {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        
        .item-title {
            font-weight: 500;
            color: #111827;
            margin-bottom: 4px;
        }
        
        .item-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 8px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .item-reference {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            padding: 6px 10px;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        
        .ref-label {
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 600;
            color: #0369a1;
            letter-spacing: 0.5px;
        }
        
        .ref-value {
            font-size: 13px;
            font-weight: 600;
            color: #0c4a6e;
            letter-spacing: 0.5px;
        }
        
        .item-reference-match {
            background: #dcfce7;
            border-color: #86efac;
            animation: pulse-green 0.5s ease-in-out;
        }
        
        .item-reference-match .ref-label {
            color: #15803d;
        }
        
        .item-reference-match .ref-value {
            color: #166534;
        }
        
        @keyframes pulse-green {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }
        
        .item-amount {
            font-family: monospace;
            font-weight: 600;
            font-size: 16px;
        }
        
        .item-amount.credit {
            color: #059669;
        }
        
        .item-amount.debit {
            color: #dc2626;
        }
        
        .item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-paid {
            background: #dcfce7;
            color: #166534;
        }
        
        .badge-failed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-refunded {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .actions {
            position: fixed;
            bottom: 40px;
            right: 40px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            min-width: 300px;
        }
        
        .actions.hidden {
            display: none;
        }
        
        .actions-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #111827;
        }
        
        .actions-detail {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
            padding: 12px;
            background: #f9fafb;
            border-radius: 6px;
        }
        
        .btn {
            display: block;
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
            margin-bottom: 8px;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: #6b7280;
        }
        
        .match-suggestion {
            background: #fefce8;
            border-left: 4px solid #facc15;
        }
        
        .match-suggestion-strong {
            background: #dcfce7;
            border-left: 4px solid #22c55e;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Réconciliation des paiements</h1>
            <p>Associez les transactions bancaires aux paiements en attente</p>
        </div>
        
        <div class="grid">
            <!-- Colonne gauche : Transactions non réconciliées -->
            <div class="panel">
                <div class="panel-header">
                    <h2>
                        Transactions bancaires
                        <span class="count">({{ $unreconciled_transactions->count() }} non réconciliées)</span>
                    </h2>
                </div>
                
                <div class="list" id="transactions-list">
                    @forelse($unreconciled_transactions as $transaction)
                        <div class="item transaction-item" 
                             data-id="{{ $transaction->id }}"
                             data-amount="{{ $transaction->amount }}"
                             data-date="{{ $transaction->date }}"
                             data-reference="{{ $transaction->structured_reference ?? $transaction->free_reference ?? '' }}"
                             onclick="selectTransaction({{ $transaction->id }})">
                            <div class="item-date">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</div>
                            <div class="item-title">{{ $transaction->counterparty_name ?? 'Sans nom' }}</div>
                            
                            @if($transaction->structured_reference || $transaction->free_reference)
                                <div class="item-reference">
                                    <span class="ref-label">Réf:</span>
                                    <span class="ref-value">{{ $transaction->structured_reference ?? $transaction->free_reference }}</span>
                                </div>
                            @endif
                            
                            <div class="item-subtitle">{{ Str::limit($transaction->description, 60) }}</div>
                            <div class="item-footer">
                                <span class="item-amount credit">
                                    {{ number_format($transaction->amount, 2, ',', ' ') }} €
                                </span>
                                @if($transaction->counterparty_bank_account)
                                    <span style="font-size: 11px; color: #9ca3af;">
                                        {{ substr($transaction->counterparty_bank_account, -4) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <p>Toutes les transactions sont réconciliées !</p>
                        </div>
                    @endforelse
                </div>
            </div>
            
            <!-- Colonne droite : Paiements en attente -->
            <div class="panel">
                <div class="panel-header">
                    <h2>
                        Paiements en attente
                        <span class="count">({{ $pending_payments->count() }})</span>
                    </h2>
                </div>
                
                <div class="list" id="payments-list">
                    @forelse($pending_payments as $payment)
                        <div class="item payment-item" 
                             data-id="{{ $payment->id }}"
                             data-amount="{{ $payment->amount_due }}"
                             data-reference="{{ $payment->reference }}"
                             onclick="selectPayment({{ $payment->id }})">
                            <div class="item-date">
                                Créé le {{ $payment->created_at->format('d/m/Y') }}
                            </div>
                            
                            <div class="item-reference">
                                <span class="ref-label">Réf:</span>
                                <span class="ref-value">{{ $payment->reference }}</span>
                            </div>
                            
                            <div class="item-subtitle">
                                {{ class_basename($payment->payable_type) }} #{{ $payment->payable_id }}
                            </div>
                            <div class="item-footer">
                                <span class="item-amount">
                                    {{ number_format($payment->amount_due, 2, ',', ' ') }} €
                                </span>
                                <span class="badge badge-{{ $payment->status }}">{{ $payment->status }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <p>Aucun paiement en attente</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    
    <!-- Panel d'actions flottant -->
    <div class="actions hidden" id="actions-panel">
        <div class="actions-title">Réconcilier le paiement</div>
        <div class="actions-detail" id="reconcile-details">
            Sélectionnez une transaction et un paiement
        </div>
        <form method="POST" action="{{ route('admin.transactions.reconcile.store') }}" id="reconcile-form">
            @csrf
            <input type="hidden" name="transaction_id" id="selected-transaction-id">
            <input type="hidden" name="payment_id" id="selected-payment-id">
            <button type="submit" class="btn btn-primary">Confirmer la réconciliation</button>
            <button type="button" class="btn btn-secondary" onclick="clearSelection()">Annuler</button>
        </form>
    </div>
    
    <script>
        let selectedTransaction = null;
        let selectedPayment = null;
        
        function selectTransaction(id) {
            // Désélectionner précédente
            document.querySelectorAll('.transaction-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Sélectionner nouvelle
            const item = document.querySelector(`.transaction-item[data-id="${id}"]`);
            item.classList.add('selected');
            
            selectedTransaction = {
                id: id,
                amount: parseFloat(item.dataset.amount),
                date: item.dataset.date,
                reference: item.dataset.reference || ''
            };
            
            updateActionsPanel();
            highlightMatches();
        }
        
        function selectPayment(id) {
            // Désélectionner précédente
            document.querySelectorAll('.payment-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Sélectionner nouvelle
            const item = document.querySelector(`.payment-item[data-id="${id}"]`);
            item.classList.add('selected');
            
            selectedPayment = {
                id: id,
                amount: parseFloat(item.dataset.amount),
                reference: item.dataset.reference || ''
            };
            
            updateActionsPanel();
            highlightMatches();
        }
        
        function updateActionsPanel() {
            const panel = document.getElementById('actions-panel');
            const details = document.getElementById('reconcile-details');
            
            if (selectedTransaction && selectedPayment) {
                panel.classList.remove('hidden');
                
                const amountMatch = Math.abs(selectedTransaction.amount - selectedPayment.amount) < 0.01;
                const refMatch = selectedTransaction.reference && selectedPayment.reference && 
                                selectedTransaction.reference.trim().toUpperCase() === selectedPayment.reference.trim().toUpperCase();
                
                let matchHtml = '<div style="margin-top: 8px;">';
                
                if (refMatch) {
                    matchHtml += '<div style="color: #059669; font-weight: 600;">✓ Références identiques</div>';
                } else if (selectedTransaction.reference && selectedPayment.reference) {
                    matchHtml += '<div style="color: #dc2626;">⚠ Références différentes</div>';
                }
                
                if (amountMatch) {
                    matchHtml += '<div style="color: #059669;">✓ Montants identiques</div>';
                } else {
                    matchHtml += '<div style="color: #dc2626;">⚠ Montants différents</div>';
                }
                
                matchHtml += '</div>';
                
                details.innerHTML = `
                    <div><strong>Transaction:</strong> ${selectedTransaction.amount.toFixed(2)} €</div>
                    ${selectedTransaction.reference ? `<div style="font-size: 11px; color: #6b7280;">Réf: ${selectedTransaction.reference}</div>` : '<div style="font-size: 11px; color: #dc2626;">⚠ Aucune référence</div>'}
                    <div style="margin-top: 8px;"><strong>Paiement:</strong> ${selectedPayment.amount.toFixed(2)} €</div>
                    ${selectedPayment.reference ? `<div style="font-size: 11px; color: #6b7280;">Réf: ${selectedPayment.reference}</div>` : '<div style="font-size: 11px; color: #dc2626;">⚠ Aucune référence</div>'}
                    ${matchHtml}
                `;
                
                document.getElementById('selected-transaction-id').value = selectedTransaction.id;
                document.getElementById('selected-payment-id').value = selectedPayment.id;
            } else {
                panel.classList.add('hidden');
            }
        }
        
        function highlightMatches() {
            // Supprimer les suggestions précédentes
            document.querySelectorAll('.payment-item').forEach(item => {
                item.classList.remove('match-suggestion', 'match-suggestion-strong');
                const refBlock = item.querySelector('.item-reference');
                if (refBlock) refBlock.classList.remove('item-reference-match');
            });
            
            document.querySelectorAll('.transaction-item').forEach(item => {
                const refBlock = item.querySelector('.item-reference');
                if (refBlock) refBlock.classList.remove('item-reference-match');
            });
            
            let bestMatchPayment = null;
            let bestMatchTransaction = null;
            
            // Si une transaction est sélectionnée, suggérer les correspondances
            if (selectedTransaction) {
                const transactionRef = selectedTransaction.reference.trim().toUpperCase();
                
                document.querySelectorAll('.payment-item').forEach(item => {
                    const paymentAmount = parseFloat(item.dataset.amount);
                    const paymentRef = (item.dataset.reference || '').trim().toUpperCase();
                    const refBlock = item.querySelector('.item-reference');
                    
                    // Correspondance parfaite de référence = highlight vert fort
                    if (transactionRef && paymentRef && transactionRef === paymentRef) {
                        item.classList.add('match-suggestion-strong');
                        if (refBlock) refBlock.classList.add('item-reference-match');
                        if (!bestMatchPayment) bestMatchPayment = item;
                    }
                    // Correspondance de montant uniquement = highlight jaune léger
                    else if (Math.abs(selectedTransaction.amount - paymentAmount) < 0.01) {
                        item.classList.add('match-suggestion');
                        if (!bestMatchPayment) bestMatchPayment = item;
                    }
                    // Sinon, l'item reste visible normalement sans highlight
                });
                
                // Scroll vers le meilleur match
                if (bestMatchPayment) {
                    scrollToElement(bestMatchPayment, 'payments-list');
                }
            }
            
            // Si un paiement est sélectionné, highlight les références correspondantes
            if (selectedPayment) {
                const paymentRef = selectedPayment.reference.trim().toUpperCase();
                
                document.querySelectorAll('.transaction-item').forEach(item => {
                    const transactionRef = (item.dataset.reference || '').trim().toUpperCase();
                    const refBlock = item.querySelector('.item-reference');
                    
                    // Correspondance de référence
                    if (paymentRef && transactionRef && paymentRef === transactionRef) {
                        if (refBlock) refBlock.classList.add('item-reference-match');
                        if (!bestMatchTransaction) bestMatchTransaction = item;
                    }
                });
                
                // Scroll vers le meilleur match
                if (bestMatchTransaction) {
                    scrollToElement(bestMatchTransaction, 'transactions-list');
                }
            }
        }
        
        function scrollToElement(element, containerId) {
            const container = document.getElementById(containerId);
            const elementRect = element.getBoundingClientRect();
            const containerRect = container.getBoundingClientRect();
            
            // Calculer la position pour centrer l'élément dans le container
            const elementTop = element.offsetTop;
            const elementHeight = element.offsetHeight;
            const containerHeight = container.clientHeight;
            
            // Position pour centrer l'élément
            const scrollTo = elementTop - (containerHeight / 2) + (elementHeight / 2);
            
            // Scroll avec animation douce
            container.scrollTo({
                top: scrollTo,
                behavior: 'smooth'
            });
        }
        
        function clearSelection() {
            selectedTransaction = null;
            selectedPayment = null;
            
            document.querySelectorAll('.item').forEach(item => {
                item.classList.remove('selected', 'match-suggestion', 'match-suggestion-strong');
            });
            
            document.querySelectorAll('.item-reference').forEach(ref => {
                ref.classList.remove('item-reference-match');
            });
            
            updateActionsPanel();
        }
    </script>
</body>
</html>