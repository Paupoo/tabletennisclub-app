<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-header :title="__('Supporting documents')" :subtitle="__('Invoices, tickets and letters behind the money the website does not see')" separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search a counterparty, a label, a reference...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
            @can('create', \App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::class)
                <x-button :label="__('New document')" icon="o-plus" class="btn-primary btn-sm" wire:click="create" />
            @endcan
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-300 lg:hidden" x-show="mobileSearchOpen" style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search a counterparty, a label, a reference...') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- Stats --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-admin.shared.stat-card
            :label="__('Open debts')"
            :value="number_format($this->stats['debts_total'], 2, ',', ' ') . ' €'"
            :hint="trans_choice(':count invoice to pay|:count invoices to pay', $this->stats['debts_count'])"
            icon="o-arrow-up-tray"
            :color="$this->stats['debts_count'] > 0 ? 'warning' : 'neutral'" />

        <x-admin.shared.stat-card
            :label="__('Open receivables')"
            :value="number_format($this->stats['receivables_total'], 2, ',', ' ') . ' €'"
            :hint="trans_choice(':count income awaited|:count incomes awaited', $this->stats['receivables_count'])"
            icon="o-arrow-down-tray"
            :color="$this->stats['receivables_count'] > 0 ? 'info' : 'neutral'" />

        <x-admin.shared.stat-card
            :label="__('Documents :year', ['year' => $this->stats['year']])"
            :value="$this->stats['year_count']"
            :hint="trans_choice(':count settled|:count settled', $this->stats['year_settled'])"
            icon="o-document-check"
            color="success" />
    </div>

    {{-- ── Mobile ── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden" data-mobile-list>
        @forelse ($this->documents as $document)
            @php
                $state = $document->state();
            @endphp
            <button type="button" wire:key="mobile-document-{{ $document->id }}" wire:click="show({{ $document->id }})"
                class="block w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-3 text-left">
                <span class="flex items-start justify-between gap-3">
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $document->counterparty }}</span>
                        <span class="block truncate text-xs text-muted">{{ $document->category()->label() }} · {{ $document->label }}</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span @class(['block font-bold tabular-nums', 'text-error' => $document->isExpense(), 'text-success' => $document->isIncome()])>
                            {{ $document->isExpense() ? '−' : '+' }}{{ number_format($document->amount, 2, ',', ' ') }} €
                        </span>
                        <x-badge :value="$state->label()" class="badge-sm {{ $state->badgeClass() }}" />
                    </span>
                </span>
                <span class="mt-1 block text-xs text-muted">
                    <span class="font-mono">{{ $document->reference() }}</span> · {{ $document->date->format('d/m/Y') }}
                </span>
            </button>
        @empty
            <x-admin.shared.list-empty-state icon="o-document-check"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No supporting documents to display.')" />
        @endforelse

        <div>{{ $this->documents->links() }}</div>
    </div>

    {{-- ── Desktop ── --}}
    <x-card class="hidden bg-base-100 shadow-sm lg:block">
        @if ($this->documents->isEmpty())
            <x-admin.shared.list-empty-state icon="o-document-check"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No supporting documents to display.')" />
        @else
            <x-table :headers="$headers" :rows="$this->documents" with-pagination hover
                @row-click="$wire.show($event.detail.id)">
                @scope('cell_reference', $document)
                    <span class="font-mono text-xs">{{ $document->reference() }}</span>
                @endscope

                @scope('cell_date', $document)
                    <span class="text-sm tabular-nums">{{ $document->date->format('d/m/Y') }}</span>
                @endscope

                @scope('cell_counterparty', $document)
                    <div class="max-w-xs">
                        <div class="truncate font-medium">{{ $document->counterparty }}</div>
                        <div class="truncate text-xs text-muted">{{ $document->label }}</div>
                    </div>
                @endscope

                @scope('cell_category', $document)
                    <span class="text-sm">{{ $document->category()->label() }}</span>
                    <div class="text-xs text-muted">{{ $document->isExpense() ? __('Money out') : __('Money in') }}</div>
                @endscope

                @scope('cell_amount', $document)
                    <span @class(['tabular-nums font-bold', 'text-error' => $document->isExpense(), 'text-success' => $document->isIncome()])>
                        {{ $document->isExpense() ? '−' : '+' }}{{ number_format($document->amount, 2, ',', ' ') }} €
                    </span>
                @endscope

                @scope('cell_state', $document)
                    @php
                        $state = $document->state();
                    @endphp
                    <x-badge :value="$state->label()" class="badge-sm {{ $state->badgeClass() }}" />
                    @if ($document->hasAmountMismatch())
                        <x-icon name="o-exclamation-triangle" class="ms-1 size-4 text-warning"
                            :title="__('The linked movements add up to :amount €', ['amount' => number_format($document->linkedAmount(), 2, ',', ' ')])" />
                    @endif
                @endscope
            </x-table>
        @endif
    </x-card>

    {{-- Filter drawer --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <x-select wire:model.live="stateFilter" :label="__('Status')" :placeholder="__('All')"
                :options="$stateOptions" />
            <x-select wire:model.live="categoryFilter" :label="__('Category')" :placeholder="__('All categories')"
                :options="$categoryOptions" />
            <x-select wire:model.live="fiscalYear" :label="__('Financial year')" :placeholder="__('Every year')"
                :options="$yearOptions" :hint="__('The date printed on the document.')" />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- Reading drawer: the document, its files, and what paid it --}}
    <x-drawer wire:model.live="readerDrawer" :title="__('Supporting document')" right with-close-button class="w-full max-w-3xl">
        @if ($this->shown)
            @php
                $shown = $this->shown;
                $shownState = $shown->state();
                $canLinkBank = auth()->user()->can('linkTransaction', \App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::class);
                $canLinkCash = auth()->user()->can('linkCashRegisterEntry', $shown);
            @endphp
            <div class="space-y-5" wire:key="shown-document-{{ $shown->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold">{{ $shown->counterparty }}</div>
                        <div class="text-sm text-muted"><span class="font-mono">{{ $shown->reference() }}</span> · {{ $shown->label }}</div>
                    </div>
                    <x-badge :value="$shownState->label()" class="{{ $shownState->badgeClass() }}" />
                </div>

                @if ($shown->hasAmountMismatch())
                    <div class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm" data-amount-mismatch>
                        <x-icon name="o-exclamation-triangle" class="me-1 size-4 text-warning" />
                        {{ __('The linked movements add up to :linked €, the document to :amount €. Check nothing is missing — or that a movement pays several documents.', ['linked' => number_format($shown->linkedAmount(), 2, ',', ' '), 'amount' => number_format($shown->amount, 2, ',', ' ')]) }}
                    </div>
                @endif

                <div class="divide-y divide-base-200 rounded-xl border border-base-300 text-sm">
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Category') }}</span>
                        <span>{{ ($shown->isExpense() ? __('Money out') : __('Money in')) . ' — ' . $shown->category()->label() }}</span>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Date of the document') }}</span>
                        <span>{{ $shown->date->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Amount') }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($shown->amount, 2, ',', ' ') }} €</span>
                    </div>
                    @if ($shown->createdBy)
                        <div class="flex justify-between gap-4 p-3">
                            <span class="text-muted">{{ __('Filed by') }}</span>
                            <span>{{ $shown->createdBy->full_name }} · {{ $shown->created_at?->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>

                {{-- What paid it --}}
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Paid by') }}</p>
                    @if ($shown->transactions->isEmpty() && $shown->cashRegisterEntries->isEmpty())
                        <p class="text-sm text-muted">
                            {{ $shown->isExpense() ? __('Nothing yet: the club still owes this amount.') : __('Nothing yet: the club is still owed this amount.') }}
                        </p>
                    @endif
                    @foreach ($shown->transactions as $transaction)
                        <div wire:key="linked-transaction-{{ $transaction->id }}" class="flex items-center gap-3 rounded-lg border border-success/20 bg-success/5 p-2.5 text-sm">
                            <x-icon name="o-building-library" class="h-4 w-4 shrink-0 text-success" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold">{{ $transaction->counterparty_name ?: $transaction->description }}</div>
                                <div class="text-xs text-muted">{{ $transaction->date->format('d/m/Y') }}@if ($transaction->bankAccount) · {{ $transaction->bankAccount->name }}@endif</div>
                            </div>
                            <span class="shrink-0 font-bold tabular-nums">{{ number_format($transaction->amount, 2, ',', ' ') }} €</span>
                            @if ($canLinkBank)
                                <x-button :label="__('Unlink')" class="btn-ghost btn-xs" wire:click="unlinkTransaction({{ $transaction->id }})"
                                    spinner="unlinkTransaction({{ $transaction->id }})" />
                            @endif
                        </div>
                    @endforeach
                    @foreach ($shown->cashRegisterEntries as $entry)
                        <div wire:key="linked-entry-{{ $entry->id }}" class="flex items-center gap-3 rounded-lg border border-success/20 bg-success/5 p-2.5 text-sm">
                            <x-icon name="o-currency-euro" class="h-4 w-4 shrink-0 text-success" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-semibold">{{ $entry->cashRegister?->name }} — {{ $entry->reason }}</div>
                                <div class="text-xs text-muted">{{ $entry->created_at?->format('d/m/Y') }}</div>
                            </div>
                            <span class="shrink-0 font-bold tabular-nums">{{ number_format($entry->amount / 100, 2, ',', ' ') }} €</span>
                            @if ($canLinkCash)
                                <x-button :label="__('Unlink')" class="btn-ghost btn-xs" wire:click="unlinkCashEntry({{ $entry->id }})"
                                    spinner="unlinkCashEntry({{ $entry->id }})" />
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Link a bank line: the suggestions first, then the search --}}
                @if ($canLinkBank)
                    <div class="space-y-2 rounded-xl border border-base-300 p-3" data-link-transactions>
                        <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Link bank transactions') }}</p>
                        @foreach ($this->transactionSuggestions as $candidate)
                            <div wire:key="suggested-transaction-{{ $candidate->id }}" class="flex items-center gap-3 rounded-lg border border-success/30 bg-success/5 p-2.5 text-sm">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold">{{ $candidate->counterparty_name ?: $candidate->description }}</div>
                                    <div class="text-xs text-muted">{{ $candidate->date->format('d/m/Y') }} · {{ \Illuminate\Support\Str::limit($candidate->description, 60) }}</div>
                                </div>
                                <span class="shrink-0 font-bold tabular-nums">{{ number_format($candidate->amount, 2, ',', ' ') }} €</span>
                                <x-button :label="__('Link')" icon="o-link" class="btn-outline btn-xs" wire:click="linkTransaction({{ $candidate->id }})"
                                    spinner="linkTransaction({{ $candidate->id }})" />
                            </div>
                        @endforeach
                        @if ($this->transactionSuggestions->isEmpty())
                            <p class="text-xs text-muted">{{ __('No transaction of the same amount within :days days. Search below.', ['days' => \App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::SUGGESTION_WINDOW_DAYS]) }}</p>
                        @endif
                        <x-input :placeholder="__('Search a counterparty, a description or an amount...')" icon="o-magnifying-glass"
                            wire:model.live.debounce.300ms="linkSearch" clearable />
                        @foreach ($this->transactionSearchResults as $candidate)
                            <div wire:key="found-transaction-{{ $candidate->id }}" class="flex items-center gap-3 rounded-lg border border-base-300 p-2.5 text-sm">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold">{{ $candidate->counterparty_name ?: $candidate->description }}</div>
                                    <div class="text-xs text-muted">{{ $candidate->date->format('d/m/Y') }} · {{ \Illuminate\Support\Str::limit($candidate->description, 60) }}</div>
                                </div>
                                <span class="shrink-0 font-bold tabular-nums">{{ number_format($candidate->amount, 2, ',', ' ') }} €</span>
                                <x-button :label="__('Link')" icon="o-link" class="btn-ghost btn-xs" wire:click="linkTransaction({{ $candidate->id }})"
                                    spinner="linkTransaction({{ $candidate->id }})" />
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Paid from the till --}}
                @if ($canLinkCash)
                    <div class="space-y-2 rounded-xl border border-base-300 p-3" data-pay-in-cash>
                        <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Paid in cash') }}</p>
                        @foreach ($this->cashEntrySuggestions as $entry)
                            <div wire:key="suggested-entry-{{ $entry->id }}" class="flex items-center gap-3 rounded-lg border border-success/30 bg-success/5 p-2.5 text-sm">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold">{{ $entry->cashRegister?->name }} — {{ $entry->reason }}</div>
                                    <div class="text-xs text-muted">{{ $entry->created_at?->format('d/m/Y') }}</div>
                                </div>
                                <span class="shrink-0 font-bold tabular-nums">{{ number_format($entry->amount / 100, 2, ',', ' ') }} €</span>
                                <x-button :label="__('Link')" icon="o-link" class="btn-outline btn-xs" wire:click="linkCashEntry({{ $entry->id }})"
                                    spinner="linkCashEntry({{ $entry->id }})" />
                            </div>
                        @endforeach
                        @can('cash_register.entry.create')
                            @if (count($this->cashRegisterOptions) > 0)
                                <div class="flex flex-wrap items-end gap-2">
                                    <div class="min-w-48 flex-1">
                                        <x-select wire:model="cashRegisterId" :label="__('Cash register')" :options="$this->cashRegisterOptions" />
                                    </div>
                                    <x-button :label="__('Record a cash payment of :amount €', ['amount' => number_format($shown->amount, 2, ',', ' ')])"
                                        icon="o-currency-euro" class="btn-outline btn-sm" wire:click="payInCash" spinner="payInCash" />
                                </div>
                            @endif
                        @endcan
                    </div>
                @endif

                <x-admin.treasury.supporting-document-files :document="$shown" />
            </div>

            <x-slot:actions>
                @can('delete', $shown)
                    @unless ($shown->isSettled())
                        <x-button :label="__('Delete')" icon="o-trash" class="btn-ghost text-error" wire:click="openDelete" />
                    @endunless
                @endcan
                @can('update', $shown)
                    <x-button :label="__('Edit')" icon="o-pencil" class="btn-primary" wire:click="edit" />
                @endcan
            </x-slot:actions>
        @endif
    </x-drawer>

    {{-- Filing or correcting a document --}}
    <x-drawer wire:model="formDrawer" :title="$editingDocumentId ? __('Edit the supporting document') : __('New supporting document')" right with-close-button class="w-full max-w-xl">
        <x-form wire:submit="save">
            <x-admin.treasury.supporting-document-form :editing="$this->editingDocument()" :files="$documentFiles" :removed-file-ids="$removedDocumentFileIds" />

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="$set('formDrawer', false)" />
                <x-button class="btn-primary" :label="__('Save')" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    <x-confirm-modal
        model="deleteModal"
        :title="__('Delete this supporting document')"
        :confirm-label="__('Delete')"
        confirmClass="btn-error"
        confirmAction="confirmDelete" :open="$deleteModal">
        <p class="text-sm">{{ __('It justifies no movement. It goes to the bin, with its files.') }}</p>
    </x-confirm-modal>
</div>
