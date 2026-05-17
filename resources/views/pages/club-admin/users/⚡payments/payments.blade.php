<div>
    <x-header title="{{ __('Treasury') }}" subtitle="{{ __('Payment tracking') }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input
                placeholder="{{ __('Search ref. or name...') }}"
                wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass"
                class="border-none bg-base-200 w-64" />
        </x-slot:middle>
        <x-slot:actions>
            @if($statusFilter === 'to_refund')
            <x-button
                label="{{ __('Auto-match refunds') }}"
                icon="o-sparkles"
                class="btn-error btn-sm"
                wire:click="previewBatchRefundMatch"
                spinner />
            @else
            <x-button
                label="{{ __('Auto-match') }}"
                icon="o-sparkles"
                class="btn-primary btn-sm"
                wire:click="previewBatchMatch"
                spinner />
            @endif
            <x-button
                label="{{ __('Import CSV') }}"
                icon="o-arrow-up-tray"
                class="btn-outline btn-sm"
                wire:click="$set('importModal', true)" />
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-card class="border border-warning/20 bg-warning/5" shadow>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-50">{{ __('Pending') }}</div>
                    <div class="text-2xl font-black mt-1">{{ number_format($this->stats['pending_total'], 2, ',', ' ') }} €</div>
                    <div class="text-xs opacity-60 mt-0.5">{{ $this->stats['pending_count'] }} {{ __('payment(s) awaiting reconciliation') }}</div>
                </div>
                <x-icon name="o-clock" class="w-10 h-10 text-warning opacity-40" />
            </div>
        </x-card>

        <x-card class="border border-success/20 bg-success/5" shadow>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-50">{{ __('Collected') }}</div>
                    <div class="text-2xl font-black mt-1">{{ number_format($this->stats['paid_total'], 2, ',', ' ') }} €</div>
                    <div class="text-xs opacity-60 mt-0.5">{{ $this->stats['paid_count'] }} {{ __('payment(s) received') }}</div>
                </div>
                <x-icon name="o-check-badge" class="w-10 h-10 text-success opacity-40" />
            </div>
        </x-card>

        <x-card
            wire:click="$set('statusFilter', 'to_refund')"
            @class([
                'border cursor-pointer transition-all',
                'border-error/40 bg-error/10 shadow-md ring-2 ring-error/30' => $this->stats['to_refund_count'] > 0,
                'border-base-200 bg-base-100 opacity-60'                     => $this->stats['to_refund_count'] === 0,
            ])
            shadow>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-50">{{ __('To refund') }}</div>
                    <div @class([
                        'text-2xl font-black mt-1',
                        'text-error' => $this->stats['to_refund_count'] > 0,
                    ])>{{ number_format($this->stats['to_refund_total'], 2, ',', ' ') }} €</div>
                    <div class="text-xs opacity-60 mt-0.5">{{ $this->stats['to_refund_count'] }} {{ __('refund(s) pending') }}</div>
                </div>
                <x-icon name="o-arrow-uturn-left" @class([
                    'w-10 h-10 opacity-40',
                    'text-error'    => $this->stats['to_refund_count'] > 0,
                    'text-base-400' => $this->stats['to_refund_count'] === 0,
                ]) />
            </div>
        </x-card>
    </div>

    {{-- Tabs + table --}}
    <x-tabs wire:model.live="statusFilter">
        <x-tab name="pending"   label="{{ __('Pending') }}"   icon="o-clock" />
        <x-tab name="paid"      label="{{ __('Paid') }}"      icon="o-check-badge" />
        <x-tab name="to_refund" label="{{ __('To refund') }}" icon="o-arrow-uturn-left" />
    </x-tabs>

    <x-card class="bg-base-100 border-none shadow-sm rounded-t-none">
        <x-table :headers="$headers" :rows="$payments" :sort-by="$sortBy" hover>

            @scope('cell_reference', $payment)
            <span class="font-mono text-sm tracking-tight text-primary">{{ $payment->reference }}</span>
            @endscope

            @scope('cell_member', $payment)
            <div>
                <span class="font-medium">{{ $payment->member }}</span>
                @if ($payment->event_name)
                    <div class="text-[10px] opacity-50 mt-0.5">
                        <span class="font-medium">{{ $payment->event_type }}</span>
                        · {{ $payment->event_name }}
                    </div>
                @endif
            </div>
            @endscope

            @scope('cell_amount_due', $payment)
            @if($this->statusFilter === 'paid')
            <span class="tabular-nums font-bold">{{ number_format($payment->amount_paid, 2, ',', ' ') }} €</span>
            @else
            <span class="tabular-nums font-bold">{{ number_format($payment->amount_due, 2, ',', ' ') }} €</span>
            @endif
            @endscope

            @scope('cell_created_at', $payment)
            <span class="text-xs opacity-60">{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y') }}</span>
            @endscope

            @scope('cell_iban', $payment)
            @if($payment->iban)
                <span class="font-mono text-xs">{{ $payment->iban }}</span>
            @else
                <x-badge value="{{ __('Missing') }}" class="badge-warning badge-sm" icon="o-exclamation-triangle" />
            @endif
            @endscope

            @scope('actions', $payment)
            @if($this->statusFilter === 'pending')
            <div class="flex items-center gap-2">
                <x-button
                    icon="o-paper-airplane"
                    wire:click="sendReminder({{ $payment->id }})"
                    class="btn-xs btn-ghost"
                    tooltip="{{ $payment->invitation_counter > 0 ? __('Resend (:n sent)', ['n' => $payment->invitation_counter]) : __('Send invitation') }}"
                    spinner />
                <x-button
                    label="{{ __('Reconcile') }}"
                    icon="o-link"
                    wire:click="openReconcile({{ $payment->id }})"
                    class="btn-xs btn-outline" />
            </div>
            @elseif($this->statusFilter === 'to_refund')
            <x-button
                label="{{ __('Confirm refund') }}"
                icon="o-arrow-uturn-left"
                wire:click="openRefundReconcile({{ $payment->id }})"
                class="btn-xs btn-error btn-outline" />
            @else
            <div class="flex items-center gap-1.5 text-success text-xs font-bold">
                <x-icon name="o-check-circle" class="w-4 h-4" />
                {{ __('Paid') }}
            </div>
            @endif
            @endscope

        </x-table>

        @if($payments->total() === 0)
        <div class="flex flex-col items-center justify-center py-12 opacity-40">
            <x-icon name="o-banknotes" class="w-12 h-12 mb-4" />
            <p class="text-sm italic">{{ __('No payments to display.') }}</p>
        </div>
        @endif

        <div class="mt-4">
            {{ $payments->links() }}
        </div>
    </x-card>


    {{-- ========================================== --}}
    {{-- Modal : Réconciliation                     --}}
    {{-- ========================================== --}}
    <x-modal wire:model="reconcileModal" title="{{ __('Reconcile Payment') }}" separator box-class="max-w-2xl">

        @if($currentPayment)

        {{-- Résumé du paiement à réconcilier --}}
        <div class="flex items-center gap-4 p-4 rounded-xl bg-base-200/60 border border-base-300 mb-6">
            <x-icon name="o-document-text" class="w-8 h-8 text-primary shrink-0" />
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm">{{ $currentPayment->payable?->user?->first_name }} {{ $currentPayment->payable?->user?->last_name }}</div>
                @if ($currentPayment->payable?->tournament)
                    <div class="text-xs text-primary/70 mt-0.5">
                        {{ __('Tournament') }} · {{ $currentPayment->payable->tournament->name }}
                    </div>
                @endif
                <div class="font-mono text-xs text-primary mt-0.5">{{ $currentPayment->reference }}</div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-lg font-black">{{ number_format($currentPayment->amount_due, 2, ',', ' ') }} €</div>
                <div class="text-xs opacity-50">{{ __('expected') }}</div>
            </div>
        </div>

        {{-- Liste des transactions non réconciliées --}}
        <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
            <div class="text-xs font-bold uppercase tracking-widest opacity-50 mb-3">
                {{ __('Unreconciled bank transactions') }}
            </div>

            @forelse($pendingTransactions as $transaction)
            @php
                $score = $transaction->match_score ?? 'none';
                $isPerfect = $score === 'perfect';
            @endphp
            <div
                wire:click="$set('selectedTransactionId', {{ $transaction->id }})"
                @class([
                    'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all duration-150',
                    'border-primary bg-primary/5 shadow-sm'   => $selectedTransactionId === $transaction->id,
                    'border-success/60 bg-success/5'          => $selectedTransactionId !== $transaction->id && $isPerfect,
                    'border-base-200 hover:border-base-300 bg-base-100' => $selectedTransactionId !== $transaction->id && !$isPerfect,
                ])>

                {{-- Sélecteur visuel --}}
                <div @class([
                    'w-4 h-4 rounded-full border-2 shrink-0 flex items-center justify-center',
                    'border-primary bg-primary' => $selectedTransactionId === $transaction->id,
                    'border-base-300'           => $selectedTransactionId !== $transaction->id,
                ])>
                    @if($selectedTransactionId === $transaction->id)
                    <div class="w-2 h-2 rounded-full bg-white"></div>
                    @endif
                </div>

                {{-- Infos transaction --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm truncate">{{ $transaction->counterparty_name ?? '—' }}</span>
                        @if($score === 'perfect')
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-success bg-success/15 px-1.5 py-0.5 rounded">{{ __('Perfect match') }}</span>
                        @elseif($score === 'reference')
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-info bg-info/15 px-1.5 py-0.5 rounded">{{ __('Ref. match') }}</span>
                        @elseif($score === 'amount')
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-warning bg-warning/15 px-1.5 py-0.5 rounded">{{ __('Amount match') }}</span>
                        @endif
                    </div>
                    @if($transaction->structured_reference)
                    <div class="font-mono text-xs text-primary mt-0.5">{{ $transaction->structured_reference }}</div>
                    @elseif($transaction->free_reference)
                    <div class="text-xs opacity-50 mt-0.5 truncate italic">{{ $transaction->free_reference }}</div>
                    @endif
                </div>

                {{-- Date + Montant --}}
                <div class="text-right shrink-0">
                    <div class="font-bold tabular-nums">{{ number_format($transaction->amount, 2, ',', ' ') }} €</div>
                    <div class="text-xs opacity-50">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</div>
                </div>
            </div>

            @empty
            <div class="flex flex-col items-center justify-center py-10 opacity-40">
                <x-icon name="o-inbox" class="w-10 h-10 mb-3" />
                <p class="text-sm italic">{{ __('No unreconciled transactions. Import a bank statement first.') }}</p>
            </div>
            @endforelse
        </div>

        @endif

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.reconcileModal = false" class="btn-ghost" />
            <x-button
                label="{{ __('Confirm Reconciliation') }}"
                icon="o-check"
                class="btn-primary"
                wire:click="confirmReconcile"
                :disabled="! $selectedTransactionId"
                spinner />
        </x-slot:actions>
    </x-modal>


    {{-- ========================================== --}}
    {{-- Modal : Batch Auto-Réconciliation          --}}
    {{-- ========================================== --}}
    <x-modal wire:model="batchModal" title="{{ __('Auto-match — Confirm reconciliations') }}" separator box-class="max-w-2xl">

        <div class="space-y-4">
            <div class="flex items-start gap-3 p-3 rounded-xl bg-success/10 border border-success/20 text-sm">
                <x-icon name="o-sparkles" class="w-5 h-5 text-success shrink-0 mt-0.5" />
                <span>
                    {{ __(':count perfect match(es) found — structured reference and amount match exactly. Confirm to reconcile all at once.', ['count' => count($batchMatches)]) }}
                </span>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @foreach($batchMatches as $match)
                <div class="flex items-center gap-4 p-3 rounded-xl bg-base-100 border border-base-200">
                    <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm">{{ $match['member'] }}</div>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="font-mono text-xs text-primary">{{ $match['reference'] }}</span>
                            <span class="text-xs opacity-40">·</span>
                            <span class="text-xs opacity-60">{{ $match['counterparty'] }}</span>
                            <span class="text-xs opacity-40">·</span>
                            <span class="text-xs opacity-60">{{ \Carbon\Carbon::parse($match['transaction_date'])->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    <span class="font-black tabular-nums text-success">{{ number_format($match['amount'], 2, ',', ' ') }} €</span>
                </div>
                @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.batchModal = false" class="btn-ghost" />
            <x-button
                label="{{ __('Confirm all (:count)', ['count' => count($batchMatches)]) }}"
                icon="o-check-badge"
                class="btn-success"
                wire:click="confirmBatchReconcile"
                spinner />
        </x-slot:actions>
    </x-modal>


    {{-- ========================================== --}}
    {{-- Modal : Réconciliation remboursement       --}}
    {{-- ========================================== --}}
    <x-modal wire:model="refundModal" title="{{ __('Confirm Refund') }}" separator box-class="max-w-2xl">

        @if($currentRefundPayment)

        <div class="flex items-center gap-4 p-4 rounded-xl bg-error/5 border border-error/20 mb-6">
            <x-icon name="o-arrow-uturn-left" class="w-8 h-8 text-error shrink-0" />
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm">{{ $currentRefundPayment->payable?->user?->full_name }}</div>
                @if ($currentRefundPayment->payable?->tournament)
                    <div class="text-xs text-primary/70 mt-0.5">
                        {{ __('Tournament') }} · {{ $currentRefundPayment->payable->tournament->name }}
                    </div>
                @endif
                <div class="font-mono text-xs text-primary mt-0.5">{{ $currentRefundPayment->reference }}</div>
                @if($currentRefundPayment->payable?->user?->iban)
                <div class="text-xs text-base-content/60 mt-0.5">IBAN : <span class="font-mono">{{ $currentRefundPayment->payable->user->iban }}</span></div>
                @endif
            </div>
            <div class="text-right shrink-0">
                <div class="text-lg font-black text-error">{{ number_format($currentRefundPayment->amount_paid, 2, ',', ' ') }} €</div>
                <div class="text-xs opacity-50">{{ __('to refund') }}</div>
            </div>
        </div>

        <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
            <div class="text-xs font-bold uppercase tracking-widest opacity-50 mb-3">
                {{ __('Outgoing bank transactions') }}
            </div>

            @forelse($refundTransactions as $transaction)
            @php $score = $transaction->match_score ?? 'none'; @endphp
            <div
                wire:click="$set('selectedRefundTransactionId', {{ $transaction->id }})"
                @class([
                    'flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all duration-150',
                    'border-primary bg-primary/5 shadow-sm'                            => $selectedRefundTransactionId === $transaction->id,
                    'border-success/60 bg-success/5'                                   => $selectedRefundTransactionId !== $transaction->id && $score === 'perfect',
                    'border-info/40 bg-info/5'                                         => $selectedRefundTransactionId !== $transaction->id && $score === 'iban',
                    'border-warning/40 bg-warning/5'                                   => $selectedRefundTransactionId !== $transaction->id && $score === 'amount',
                    'border-base-200 hover:border-base-300 bg-base-100'               => $selectedRefundTransactionId !== $transaction->id && $score === 'none',
                ])>

                <div @class([
                    'w-4 h-4 rounded-full border-2 shrink-0 flex items-center justify-center',
                    'border-primary bg-primary' => $selectedRefundTransactionId === $transaction->id,
                    'border-base-300'           => $selectedRefundTransactionId !== $transaction->id,
                ])>
                    @if($selectedRefundTransactionId === $transaction->id)
                    <div class="w-2 h-2 rounded-full bg-white"></div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm truncate">{{ $transaction->counterparty_name ?? '—' }}</span>
                        @if($score === 'perfect')
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-success bg-success/15 px-1.5 py-0.5 rounded">{{ __('Perfect match') }}</span>
                        @elseif($score === 'iban')
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-info bg-info/15 px-1.5 py-0.5 rounded">{{ __('IBAN match') }}</span>
                        @elseif($score === 'amount')
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-warning bg-warning/15 px-1.5 py-0.5 rounded">{{ __('Amount match') }}</span>
                        @endif
                    </div>
                    @if($transaction->counterparty_bank_account)
                    <div class="font-mono text-xs text-base-content/50 mt-0.5">{{ $transaction->counterparty_bank_account }}</div>
                    @endif
                </div>

                <div class="text-right shrink-0">
                    <div class="font-bold tabular-nums text-error">{{ number_format($transaction->amount, 2, ',', ' ') }} €</div>
                    <div class="text-xs opacity-50">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</div>
                </div>
            </div>

            @empty
            <div class="flex flex-col items-center justify-center py-10 opacity-40">
                <x-icon name="o-inbox" class="w-10 h-10 mb-3" />
                <p class="text-sm italic">{{ __('No outgoing transactions found. Import a bank statement containing the refund transfer.') }}</p>
            </div>
            @endforelse
        </div>

        @endif

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.refundModal = false" class="btn-ghost" />
            <x-button
                label="{{ __('Confirm Refund') }}"
                icon="o-arrow-uturn-left"
                class="btn-error"
                wire:click="confirmRefundReconcile"
                :disabled="! $selectedRefundTransactionId"
                spinner />
        </x-slot:actions>
    </x-modal>


    {{-- ========================================== --}}
    {{-- Modal : Batch remboursements               --}}
    {{-- ========================================== --}}
    <x-modal wire:model="refundBatchModal" title="{{ __('Auto-match refunds — Confirm') }}" separator box-class="max-w-2xl">

        <div class="space-y-4">
            <div class="flex items-start gap-3 p-3 rounded-xl bg-error/10 border border-error/20 text-sm">
                <x-icon name="o-sparkles" class="w-5 h-5 text-error shrink-0 mt-0.5" />
                <span>
                    {{ __(':count match(es) found — IBAN and amount match exactly. Confirm to mark all as refunded.', ['count' => count($refundBatchMatches)]) }}
                </span>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @foreach($refundBatchMatches as $match)
                <div class="flex items-center gap-4 p-3 rounded-xl bg-base-100 border border-base-200">
                    <x-icon name="o-arrow-uturn-left" class="w-5 h-5 text-error shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm">{{ $match['member'] }}</div>
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="font-mono text-xs text-primary">{{ $match['reference'] }}</span>
                            @if($match['iban'])
                            <span class="text-xs opacity-40">·</span>
                            <span class="font-mono text-xs opacity-60">{{ $match['iban'] }}</span>
                            @endif
                            <span class="text-xs opacity-40">·</span>
                            <span class="text-xs opacity-60">{{ \Carbon\Carbon::parse($match['transaction_date'])->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    <span class="font-black tabular-nums text-error">−{{ number_format($match['amount'], 2, ',', ' ') }} €</span>
                </div>
                @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.refundBatchModal = false" class="btn-ghost" />
            <x-button
                label="{{ __('Confirm all (:count)', ['count' => count($refundBatchMatches)]) }}"
                icon="o-arrow-uturn-left"
                class="btn-error"
                wire:click="confirmBatchRefundReconcile"
                spinner />
        </x-slot:actions>
    </x-modal>


    {{-- ========================================== --}}
    {{-- Modal : Import relevé bancaire             --}}
    {{-- ========================================== --}}
    <x-modal wire:model="importModal" title="{{ __('Import Bank Statement') }}" separator>
        <div class="space-y-4">
            <p class="text-sm opacity-70">
                {{ __('Upload your bank export (ODS, XLSX, CSV). Transactions will be imported and available for reconciliation.') }}
            </p>
            <p class="text-xs opacity-50">
                {{ __('Expected columns: Date, Montant, Description, Nom contrepartie, Numéro de compte contrepartie, Communication structurée, Communication libre') }}
            </p>
            <x-file
                wire:model="importFile"
                label="{{ __('Bank file') }}"
                accept=".ods,.xlsx,.xls,.csv,.txt"
                hint="ODS · XLSX · CSV" />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.importModal = false" class="btn-ghost" />
            <x-button
                label="{{ __('Start Import') }}"
                icon="o-arrow-up-tray"
                class="btn-primary"
                wire:click="processImport"
                :disabled="! $importFile"
                spinner />
        </x-slot:actions>
    </x-modal>

</div>
