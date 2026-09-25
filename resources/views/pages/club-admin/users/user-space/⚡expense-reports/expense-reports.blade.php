<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :subtitle="__('Money you advanced for the club, and where its refund stands')"
        :title="__('My expense reports')">
        <x-slot:actions>
            <x-admin.shared.filters-button :count="count($filterChips)" class="btn-sm" />
            <x-button :label="__('Declare an expense')" icon="o-plus" class="btn-primary btn-sm"
                wire:click="openCreate" spinner="openCreate" />
        </x-slot:actions>
    </x-header>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    @if ($this->reports->isEmpty())
        <x-admin.shared.list-empty-state
            icon="o-receipt-percent"
            :heading="__('No expense reports')"
            :filtered="count($filterChips) > 0">
            {{ __('Paid something for the club? Declare it with the receipt, the treasury refunds you by transfer.') }}
        </x-admin.shared.list-empty-state>
    @else
        <x-card class="!p-0">
            <div class="divide-y divide-base-200">
                @foreach ($this->reports as $report)
                    @php
                        $displayStatus = $report->displayStatus();
                    @endphp
                    <button type="button" wire:key="report-{{ $report->id }}"
                        class="flex w-full cursor-pointer flex-col gap-3 p-4 text-left hover:bg-base-200/40 sm:flex-row sm:items-center sm:justify-between"
                        wire:click="show({{ $report->id }})">
                        <span class="block min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="truncate font-semibold">{{ $report->description }}</span>
                                <x-badge :value="$report->category->label()" class="badge-ghost badge-sm shrink-0" />
                            </span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-base-content/60">
                                <span>{{ __('Spent on :date', ['date' => $report->spent_on->format('d/m/Y')]) }}</span>
                                <span class="text-base-content/30">·</span>
                                <span>{{ __('Declared on :date', ['date' => $report->created_at?->format('d/m/Y')]) }}</span>
                            </span>
                            @if (filled($report->decision_reason))
                                <span class="mt-1 block text-sm text-base-content/80">
                                    <span class="font-semibold">{{ __('Treasury') }} —</span> {{ $report->decision_reason }}
                                </span>
                            @endif
                        </span>
                        <span class="block text-right">
                            <span class="block font-bold tabular-nums">
                                {{ number_format($report->accepted_amount ?? $report->amount, 2, ',', ' ') }} €
                            </span>
                            @if ($report->accepted_amount !== null && $report->accepted_amount < $report->amount)
                                <span class="block text-xs tabular-nums text-muted line-through">
                                    {{ number_format($report->amount, 2, ',', ' ') }} €
                                </span>
                            @endif
                            <x-badge :value="$displayStatus->label()" class="badge-sm {{ $displayStatus->badgeClass() }}" />
                        </span>
                    </button>
                @endforeach
            </div>
        </x-card>

        <div class="mt-6">
            {{ $this->reports->links() }}
        </div>
    @endif

    {{-- Filter drawer --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Status') }}</p>
                <x-select wire:model.live="statusFilter" :placeholder="__('All statuses')"
                    :options="\App\Domains\Shared\Enums\ExpenseReportDisplayStatus::getOptions()" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Nature') }}</p>
                <x-select wire:model.live="categoryFilter" :placeholder="__('All natures')"
                    :options="\App\Domains\Shared\Enums\ExpenseCategory::getOptions()" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- Reading drawer --}}
    <x-drawer wire:model.live="readerDrawer" :title="__('Expense report')" right with-close-button class="w-full max-w-xl">
        @if ($this->shown)
            @php
                $shown = $this->shown;
                $shownStatus = $shown->displayStatus();
            @endphp
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="font-semibold">{{ $shown->description }}</div>
                        <div class="text-sm text-muted">{{ $shown->category->label() }} · {{ $shown->spent_on->format('d/m/Y') }}</div>
                    </div>
                    <x-badge :value="$shownStatus->label()" class="{{ $shownStatus->badgeClass() }}" />
                </div>

                <div class="divide-y divide-base-200 rounded-xl border border-base-300 text-sm">
                    <div class="flex justify-between p-3">
                        <span class="text-muted">{{ __('Declared amount') }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($shown->amount, 2, ',', ' ') }} €</span>
                    </div>
                    @if ($shown->accepted_amount !== null)
                        <div class="flex justify-between p-3">
                            <span class="text-muted">{{ __('Accepted amount') }}</span>
                            <span class="font-semibold tabular-nums">{{ number_format($shown->accepted_amount, 2, ',', ' ') }} €</span>
                        </div>
                    @endif
                    <div class="flex justify-between p-3">
                        <span class="text-muted">{{ __('Refund account') }}</span>
                        <span class="font-mono text-xs">{{ \App\Domains\Shared\Support\IbanNormalizer::format($shown->refund_iban) }}</span>
                    </div>
                    @if ($shown->refund?->refund_wired_at && $shownStatus !== \App\Domains\Shared\Enums\ExpenseReportDisplayStatus::Paid)
                        <div class="flex justify-between p-3">
                            <span class="text-muted">{{ __('Transfer made') }}</span>
                            <span>{{ $shown->refund->refund_wired_at->format('d/m/Y') }}</span>
                        </div>
                    @endif
                </div>

                @if (filled($shown->decision_reason))
                    <div class="rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm">
                        <div class="mb-1 font-semibold">{{ __('Treasury') }}</div>
                        {{ $shown->decision_reason }}
                    </div>
                @endif

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Proofs') }}</p>
                    @forelse ($shown->files as $file)
                        <a class="flex items-center gap-2 py-1 text-sm link" target="_blank"
                            href="{{ route('admin.expense-reports.file', $file) }}">
                            <x-icon :name="$file->isPdf() ? 'o-document-text' : 'o-photo'" class="size-4" />
                            {{ $file->original_name }}
                        </a>
                    @empty
                        <p class="text-sm text-muted">{{ __('The proofs were purged.') }}</p>
                    @endforelse
                </div>
            </div>

            <x-slot:actions>
                @can('withdraw', $shown)
                    <x-button :label="__('Withdraw')" class="btn-ghost text-error"
                        wire:click="withdraw({{ $shown->id }})"
                        wire:confirm="{{ __('Withdraw this expense report?') }}" spinner="withdraw" />
                    <x-button :label="__('Edit')" icon="o-pencil" wire:click="openEdit({{ $shown->id }})" />
                @endcan
                @can('resume', $shown)
                    <x-button :label="__('Resume this report')" icon="o-arrow-path" class="btn-primary"
                        wire:click="resume({{ $shown->id }})" />
                @endcan
            </x-slot:actions>
        @endif
    </x-drawer>

    {{-- Declare / edit drawer --}}
    <x-drawer wire:model="formDrawer" :title="$editingId ? __('Edit the expense report') : __('Declare an expense')"
        right with-close-button class="w-full max-w-xl">
        <x-form wire:submit="save">
            @if ($resumedFromId)
                <div class="rounded-xl border border-info/20 bg-info/10 p-3 text-sm">
                    {{ __('Resumed from a rejected report: check what the treasury said, and attach the right proof.') }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select wire:model="category" :label="__('Nature')" :placeholder="__('Choose…')"
                    :options="\App\Domains\Shared\Enums\ExpenseCategory::getOptions()" />
                <x-input wire:model.live.blur="amount" :label="__('Amount')" inputmode="decimal" suffix="€"
                    :hint="__('VAT included, as on the receipt')" />
            </div>

            <x-input wire:model="description" :label="__('Description')" maxlength="255"
                :placeholder="__('e.g. three boxes of balls for the youth school')" />

            <x-input wire:model.live.blur="spentOn" :label="__('Date of the expense')" type="date"
                :max="now()->toDateString()" />

            <x-input wire:model="refundIban" :label="__('Refund account (IBAN)')"
                :hint="__('Prefilled from your profile; change it if the money should go elsewhere.')" />

            <div>
                <x-file wire:model="newFiles" :label="__('Proofs')" multiple accept=".pdf,.jpg,.jpeg,.png,.webp"
                    :hint="__('Receipt and proof of payment. PDF, JPG, PNG or WebP, 10 MB each, :count at most. From an iPhone, take a screenshot or export as JPG.', ['count' => $maxFiles])" />
                @error('newFiles.*')
                    <p class="mt-1 text-sm text-error">{{ $message }}</p>
                @enderror

                @if ($this->editing)
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($this->editing->files->whereNotIn('id', $removedFileIds) as $file)
                            <li wire:key="existing-file-{{ $file->id }}" class="flex items-center justify-between gap-2">
                                <span class="truncate">{{ $file->original_name }}</span>
                                <x-button icon="o-x-mark" class="btn-ghost btn-xs" :title="__('Remove')"
                                    wire:click="removeExistingFile({{ $file->id }})" />
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if (count($newFiles) > 0)
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($newFiles as $index => $upload)
                            <li wire:key="new-file-{{ $index }}" class="flex items-center justify-between gap-2">
                                <span class="truncate">{{ $upload->getClientOriginalName() }}</span>
                                <x-button icon="o-x-mark" class="btn-ghost btn-xs" :title="__('Remove')"
                                    wire:click="removeNewFile({{ $index }})" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if ($duplicates !== [])
                <div class="rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm">
                    <p class="font-semibold">{{ __('You already declared this amount around that date.') }}</p>
                    <ul class="mt-1 list-disc ps-5">
                        @foreach ($duplicates as $duplicate)
                            <li>#{{ $duplicate['id'] }} — {{ $duplicate['description'] }} ({{ number_format($duplicate['amount'], 2, ',', ' ') }} €, {{ $duplicate['spent_on'] }})</li>
                        @endforeach
                    </ul>
                    <p class="mt-1">{{ __('Submit anyway only if it is a different expense.') }}</p>
                </div>
            @endif

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="$set('formDrawer', false)" />
                @if ($duplicates !== [])
                    <x-button class="btn-warning" :label="__('Submit anyway')" wire:click="save(true)" spinner="save" />
                @else
                    <x-button class="btn-primary" :label="$editingId ? __('Save') : __('Submit')" type="submit" spinner="save" />
                @endif
            </x-slot:actions>
        </x-form>
    </x-drawer>
</div>
