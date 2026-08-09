<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header separator :subtitle="__('Your payments and those of the members you are responsible for')"
        :title="__('My payments')">
        <x-slot:actions>
            <x-admin.shared.filters-button :count="count($filterChips)" class="btn-sm" />
        </x-slot:actions>
    </x-header>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    @php $statusStyles = ['pending' => 'badge-warning badge-soft', 'paid' => 'badge-success badge-soft', 'refunded' => 'badge-ghost', 'to_refund' => 'badge-error badge-soft']; @endphp
    @php $statusLabels = ['pending' => __('Pending'), 'paid' => __('Paid'), 'refunded' => __('Refunded'), 'to_refund' => __('To refund')]; @endphp
    @php $multiPerson = count($this->payableUsers) > 1; @endphp

    @if ($this->payments->isEmpty())
        <x-admin.shared.list-empty-state
            icon="o-credit-card"
            :heading="__('No payments')"
            :filtered="count($filterChips) > 0">
            {{ __('Your payments will appear here.') }}
        </x-admin.shared.list-empty-state>
    @else
        <x-card class="!p-0">
            <div class="divide-y divide-base-200">
                @foreach ($this->payments as $payment)
                    @php $label = $payment->payable instanceof \App\Contracts\DescribesPayment ? $payment->payable->getPaymentLabel() : null; @endphp
                    @php $isPending = $payment->status === 'pending'; @endphp
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="truncate font-semibold">{{ $label['name'] ?? '—' }}</span>
                                <x-badge :value="$label['type'] ?? '—'" class="badge-ghost badge-sm shrink-0" />
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-base-content/60">
                                @if ($multiPerson)
                                    <span class="inline-flex items-center gap-1">
                                        <x-icon name="o-user" class="size-3" />
                                        {{ $payment->payable instanceof \App\Contracts\DescribesPayment ? $payment->payable->getPayerName() : '—' }}
                                    </span>
                                    <span class="text-base-content/30">·</span>
                                @endif
                                <span>{{ $payment->created_at?->format('d/m/Y') }}</span>
                                <span class="text-base-content/30">·</span>
                                <span class="font-mono">{{ $payment->reference }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-4 sm:justify-end">
                            <div class="text-right">
                                <div class="font-bold tabular-nums">
                                    {{ number_format($payment->status === 'paid' ? $payment->amount_paid : $payment->amount_due, 2, ',', ' ') }} €
                                </div>
                                <x-badge :value="$statusLabels[$payment->status] ?? $payment->status"
                                    class="badge-sm {{ $statusStyles[$payment->status] ?? 'badge-ghost' }}" />
                            </div>
                            @if ($isPending)
                                <x-button :label="__('Pay')" icon="o-qr-code" class="btn-primary btn-sm"
                                    wire:click="openPaymentModal({{ $payment->id }})" spinner="openPaymentModal" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <div class="mt-6">
            {{ $this->payments->links() }}
        </div>
    @endif

    {{-- Filter drawer --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Status') }}</p>
                <x-select wire:model.live="statusFilter" :placeholder="__('All statuses')"
                    :options="collect($this->statusOptions())->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values()->all()" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Type') }}</p>
                <x-select wire:model.live="typeFilter" :placeholder="__('All types')"
                    :options="collect($this->typeOptions())->map(fn ($label, $id) => ['id' => $id, 'name' => $label])->values()->all()" />
            </div>
            @if ($multiPerson)
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Person') }}</p>
                    <x-select wire:model.live="personFilter" :placeholder="__('Everyone')"
                        :options="$this->payableUsers->map(fn ($u) => ['id' => $u->id, 'name' => $u->full_name])->all()" />
                </div>
            @endif
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- QR payment modal --}}
    <x-app-modal wire:model="paymentModal" :title="__('Payment details')" box-class="max-w-sm" :open="$paymentModal">
        @if ($paymentQr && $selectedPaymentId)
            @php $payment = \App\Domains\ClubAdmin\Payment\Models\Payment::find($selectedPaymentId); @endphp
            @php $label = $payment?->payable instanceof \App\Contracts\DescribesPayment ? $payment->payable->getPaymentLabel() : null; @endphp
            <div class="flex flex-col items-center gap-5">
                @if ($label)
                    <div class="w-full rounded-xl border border-primary/10 bg-primary/5 px-4 py-3 text-center">
                        <div class="mb-0.5 text-xs font-bold uppercase tracking-wide opacity-60">{{ $label['type'] }}</div>
                        <div class="text-sm font-bold text-primary">{{ $label['name'] }}</div>
                    </div>
                @endif
                <img alt="QR Code" class="h-48 w-48 rounded-xl border border-base-200 shadow" src="{{ $paymentQr }}" />
                <div class="w-full divide-y divide-base-200 text-sm">
                    <div class="flex items-center justify-between py-2">
                        <span class="opacity-60">{{ __('Amount') }}</span>
                        <span class="font-bold">{{ number_format($payment->amount_due, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="opacity-60">{{ __('Reference') }}</span>
                        <span class="font-mono text-xs">{{ $payment->reference }}</span>
                    </div>
                    @if ($this->ourClub)
                        <div class="flex items-center justify-between py-2">
                            <span class="opacity-60">IBAN</span>
                            <span class="font-mono text-xs">{{ $this->ourClub->bank_account }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="opacity-60">BIC</span>
                            <span class="font-mono text-xs">{{ $this->ourClub->bic }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="$set('paymentModal', false)" />
        </x-slot:actions>
    </x-app-modal>
</div>
