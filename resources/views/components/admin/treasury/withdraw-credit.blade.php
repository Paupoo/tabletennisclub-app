@props([
    'credit',
    'preview' => null,
])

{{--
Retirer un virement d'une créance, depuis l'une ou l'autre de ses deux listes.

Rien pour une ligne hors banque (espèces, paiement en ligne, reprise) : seul un
rapprochement bancaire se trompe de virement. La confirmation dit ce qui va
changer, pas pourquoi.
--}}
@if ($credit->transaction_id !== null)
    @can('payments.reconcile')
        @if ($preview === null)
            <x-button
                :label="__('Remove')"
                icon="o-link-slash"
                wire:click="askWithdrawCredit({{ $credit->id }})"
                class="btn-xs btn-ghost shrink-0" />
        @else
            <div {{ $attributes->class('basis-full space-y-2 rounded-lg border border-error/20 bg-error/5 p-3 text-sm') }}>
                <ul class="list-disc space-y-0.5 pl-4">
                    <li>
                        {{ __('The transfer of :date (:amount €) will be back among the lines to handle.', [
                            'date' => $credit->transaction?->date?->format('d/m/Y'),
                            'amount' => number_format($credit->amount, 2, ',', ' '),
                        ]) }}
                    </li>
                    @if ($preview['reopens_payment'])
                        <li>
                            {{ $credit->payment?->payment_method === 'refund'
                                ? __('This refund will be to pay out again.')
                                : __('This payment will be unpaid again.') }}
                        </li>
                    @endif
                    @if ($preview['written_off'] > 0)
                        <li>
                            {{ __('The :amount € written off on this transfer will be back to handle too.', [
                                'amount' => number_format($preview['written_off'], 2, ',', ' '),
                            ]) }}
                        </li>
                    @endif
                </ul>
                <div class="flex flex-wrap justify-end gap-2">
                    <x-button :label="__('Keep')" wire:click="cancelWithdrawCredit" class="btn-xs btn-ghost" />
                    <x-button
                        :label="__('Remove the transfer')"
                        icon="o-link-slash"
                        wire:click="confirmWithdrawCredit"
                        spinner="confirmWithdrawCredit"
                        class="btn-xs btn-error" />
                </div>
            </div>
        @endif
    @endcan
@endif
