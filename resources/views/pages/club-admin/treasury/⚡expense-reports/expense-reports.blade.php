<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-header :title="__('Expense reports')" :subtitle="__('Money members advanced for the club')" separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search a member or an expense...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
            @can('export', \App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport::class)
                {{-- What the screen shows — tab, search and filters — is what
                     the file holds. Prepared in the background; the bell
                     rings with the link. --}}
                <x-dropdown :label="__('Export')" icon="o-arrow-down-tray" right class="btn-ghost btn-sm">
                    <x-menu-item icon="o-printer" :title="__('Printable PDF')" wire:click="export('pdf')" spinner="export" />
                    <x-menu-item icon="o-archive-box-arrow-down" :title="__('ZIP archive (original proofs)')" wire:click="export('zip')" spinner="export" />
                    @can('archive', \App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport::class)
                        <x-menu-separator />
                        <x-menu-item icon="o-archive-box" :title="__('Archive the paid reports not archived yet')"
                            wire:click="archiveUnarchived" spinner="archiveUnarchived" />
                    @endcan
                </x-dropdown>
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
                    placeholder="{{ __('Search a member or an expense...') }}" />
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
            :label="__('To decide')"
            :value="number_format($this->stats['submitted_total'], 2, ',', ' ') . ' €'"
            :hint="trans_choice(':count report waiting|:count reports waiting', $this->stats['submitted_count'])"
            icon="o-inbox-arrow-down"
            :color="$this->stats['submitted_count'] > 0 ? 'warning' : 'neutral'" />

        <x-admin.shared.stat-card
            :label="__('Accepted, not paid yet')"
            :value="number_format($this->stats['unpaid_total'], 2, ',', ' ') . ' €'"
            :hint="trans_choice(':count refund to wire|:count refunds to wire', $this->stats['unpaid_count'])"
            icon="o-arrow-uturn-left"
            :color="$this->stats['unpaid_count'] > 0 ? 'info' : 'neutral'" />

        <x-admin.shared.stat-card
            :label="__('Paid in :year', ['year' => $this->stats['year']])"
            :value="number_format($this->stats['paid_year_total'], 2, ',', ' ') . ' €'"
            :hint="trans_choice(':count report paid|:count reports paid', $this->stats['paid_year_count'])"
            icon="o-check-circle"
            color="success" />
    </div>

    <x-admin.shared.tabs wire:model.live="statusFilter">
        {{-- In the order of the work: what waits, what is to wire, what is
             closed, what was turned down, and everything for the auditors. --}}
        <x-admin.shared.tab name="submitted" :label="__('To decide')" icon="o-inbox-arrow-down" />
        <x-admin.shared.tab name="accepted" :label="__('Accepted')" icon="o-arrow-uturn-left" />
        <x-admin.shared.tab name="paid" :label="__('Paid')" icon="o-check-circle" />
        <x-admin.shared.tab name="rejected" :label="__('Rejected')" icon="o-x-circle" />
        <x-admin.shared.tab name="all" :label="__('All')" icon="o-queue-list" />
    </x-admin.shared.tabs>

    {{-- ── Mobile ── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden" data-mobile-list>
        @forelse ($this->reports as $report)
            @php
                $status = $report->displayStatus();
            @endphp
            <button type="button" wire:key="mobile-report-{{ $report->id }}" wire:click="show({{ $report->id }})"
                class="block w-full cursor-pointer rounded-lg border border-base-300 bg-base-100 p-3 text-left">
                <span class="flex items-start justify-between gap-3">
                    <span class="min-w-0">
                        <span class="block truncate font-medium">{{ $report->user->full_name }}</span>
                        <span class="block truncate text-xs text-muted">{{ $report->category->label() }} · {{ $report->description }}</span>
                    </span>
                    <span class="shrink-0 text-right">
                        <span class="block font-bold tabular-nums">{{ number_format($report->accepted_amount ?? $report->amount, 2, ',', ' ') }} €</span>
                        <x-badge :value="$status->label()" class="badge-sm {{ $status->badgeClass() }}" />
                    </span>
                </span>
                <span class="mt-1 block text-xs text-muted">
                    {{ __('Spent on :date', ['date' => $report->spent_on->format('d/m/Y')]) }}
                    · {{ __('Declared on :date', ['date' => $report->created_at?->format('d/m/Y')]) }}
                </span>
            </button>
        @empty
            <x-admin.shared.list-empty-state icon="o-receipt-percent"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No expense reports to display.')" />
        @endforelse

        <div>{{ $this->reports->links() }}</div>
    </div>

    {{-- ── Desktop ── --}}
    <x-card class="hidden rounded-t-none bg-base-100 shadow-sm lg:block">
        @if ($this->reports->isEmpty())
            <x-admin.shared.list-empty-state icon="o-receipt-percent"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No expense reports to display.')" />
        @else
            <x-table :headers="$headers" :rows="$this->reports" with-pagination hover
                @row-click="$wire.show($event.detail.id)">
                @scope('cell_member', $report)
                    <span class="font-medium">{{ $report->user->full_name }}</span>
                @endscope

                @scope('cell_description', $report)
                    <div class="max-w-xs">
                        <div class="truncate">{{ $report->description }}</div>
                        <div class="text-xs text-muted">{{ $report->category->label() }}</div>
                    </div>
                @endscope

                @scope('cell_spent_on', $report)
                    <span class="text-sm">{{ $report->spent_on->format('d/m/Y') }}</span>
                @endscope

                @scope('cell_amount', $report)
                    <div class="tabular-nums">
                        <span class="font-bold">{{ number_format($report->accepted_amount ?? $report->amount, 2, ',', ' ') }} €</span>
                        @if ($report->accepted_amount !== null && $report->accepted_amount < $report->amount)
                            <div class="text-xs text-muted line-through">{{ number_format($report->amount, 2, ',', ' ') }} €</div>
                        @endif
                    </div>
                @endscope

                @scope('cell_created_at', $report)
                    <span class="text-xs">{{ $report->created_at?->format('d/m/Y') }}</span>
                    @if ($report->status === \App\Domains\Shared\Enums\ExpenseReportStatus::Submitted)
                        <div class="text-xs text-muted">{{ $report->created_at?->diffForHumans() }}</div>
                    @endif
                @endscope

                @scope('cell_status', $report)
                    @php
                        $status = $report->displayStatus();
                    @endphp
                    <x-badge :value="$status->label()" class="badge-sm {{ $status->badgeClass() }}" />
                    @if ($report->archived_at)
                        <x-icon name="o-archive-box" class="ms-1 size-4 text-muted" :title="__('Archived on :date', ['date' => $report->archived_at->format('d/m/Y')])" />
                    @endif
                @endscope
            </x-table>
        @endif
    </x-card>

    {{-- Filter drawer --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Member') }}</p>
                <x-choices wire:model.live="userId" single searchable clearable :options="$this->memberOptions"
                    :placeholder="__('Everyone')" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Nature') }}</p>
                <x-select wire:model.live="categoryFilter" :placeholder="__('All natures')"
                    :options="\App\Domains\Shared\Enums\ExpenseCategory::getOptions()" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Financial year') }}</p>
                <x-select wire:model.live="fiscalYear" :placeholder="__('Every year')" :options="$yearOptions"
                    :hint="__('The year the refund left the account — paid reports only.')" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Date of the expense') }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <x-input type="date" wire:model.live="dateFrom" :label="__('From')" />
                    <x-input type="date" wire:model.live="dateTo" :label="__('To')" />
                </div>
            </div>
            <x-toggle wire:model.live="unarchivedOnly" :label="__('Not archived yet')" />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- Reading and deciding drawer --}}
    <x-drawer wire:model.live="readerDrawer" :title="__('Expense report')" right with-close-button class="w-full max-w-3xl">
        @if ($this->shown)
            @php
                $shown = $this->shown;
                $shownStatus = $shown->displayStatus();
                $duplicates = $shown->possibleDuplicates();
                $sharedProofs = $shown->reportsSharingAProof();
            @endphp
            <div class="space-y-4" wire:key="shown-{{ $shown->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-lg font-semibold">{{ $shown->user->full_name }}</div>
                        <div class="text-sm text-muted">#{{ $shown->id }} · {{ __('Declared on :date', ['date' => $shown->created_at?->format('d/m/Y H:i')]) }}</div>
                    </div>
                    <x-badge :value="$shownStatus->label()" class="{{ $shownStatus->badgeClass() }}" />
                </div>

                @foreach ($duplicates as $duplicate)
                    <div class="rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm">
                        <x-icon name="o-exclamation-triangle" class="me-1 size-4 text-warning" />
                        {{ __('Possible duplicate of report #:id', ['id' => $duplicate->id]) }}
                        — {{ $duplicate->description }}, {{ number_format($duplicate->amount, 2, ',', ' ') }} €, {{ $duplicate->spent_on->format('d/m/Y') }}
                        <button type="button" class="link ms-1" wire:click="show({{ $duplicate->id }})">{{ __('Open') }}</button>
                    </div>
                @endforeach
                @foreach ($sharedProofs as $sharing)
                    <div class="rounded-lg border border-error/30 bg-error/10 p-3 text-sm">
                        <x-icon name="o-document-duplicate" class="me-1 size-4 text-error" />
                        {{ __('The same file was already used on report #:id', ['id' => $sharing->id]) }}
                        <button type="button" class="link ms-1" wire:click="show({{ $sharing->id }})">{{ __('Open') }}</button>
                    </div>
                @endforeach

                @if ($shown->user_id === auth()->id() && $shown->status === \App\Domains\Shared\Enums\ExpenseReportStatus::Submitted)
                    <div class="rounded-lg border border-info/20 bg-info/10 p-3 text-sm">
                        {{ __('To be decided by another member of the treasury.') }}
                    </div>
                @endif

                <div class="divide-y divide-base-200 rounded-xl border border-base-300 text-sm">
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Nature') }}</span>
                        <span>{{ $shown->category->label() }}</span>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Description') }}</span>
                        <span class="text-right">{{ $shown->description }}</span>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Date of the expense') }}</span>
                        <span>{{ $shown->spent_on->format('d/m/Y') }}</span>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Declared amount') }}</span>
                        <span class="font-semibold tabular-nums">{{ number_format($shown->amount, 2, ',', ' ') }} €</span>
                    </div>
                    @if ($shown->accepted_amount !== null)
                        <div class="flex justify-between gap-4 p-3">
                            <span class="text-muted">{{ __('Accepted amount') }}</span>
                            <span class="font-semibold tabular-nums">{{ number_format($shown->accepted_amount, 2, ',', ' ') }} €</span>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4 p-3">
                        <span class="text-muted">{{ __('Refund account') }}</span>
                        <span class="font-mono text-xs">{{ $this->ibanFor($shown) }}</span>
                    </div>
                    @if ($shown->decided_at)
                        <div class="flex justify-between gap-4 p-3">
                            <span class="text-muted">{{ __('Decided by') }}</span>
                            <span>{{ $shown->decider?->full_name ?? '—' }} · {{ $shown->decided_at->format('d/m/Y H:i') }}</span>
                        </div>
                    @endif
                    @if ($shown->refund)
                        <div class="flex justify-between gap-4 p-3">
                            <span class="text-muted">{{ __('Refund reference') }}</span>
                            <span class="font-mono text-xs">{{ $shown->refund->reference }}</span>
                        </div>
                        @if ($shownStatus === \App\Domains\Shared\Enums\ExpenseReportDisplayStatus::Paid)
                            <div class="flex justify-between gap-4 p-3">
                                <span class="text-muted">{{ __('Paid on') }}</span>
                                <span>{{ $shown->paidOn()?->format('d/m/Y') }}</span>
                            </div>
                        @elseif ($shown->refund->refund_wired_at)
                            <div class="flex justify-between gap-4 p-3">
                                <span class="text-muted">{{ __('Transfer made') }}</span>
                                <span>{{ $shown->refund->refund_wired_at->format('d/m/Y') }}</span>
                            </div>
                        @endif
                    @endif
                    @if ($shown->resumedFrom)
                        <div class="flex justify-between gap-4 p-3">
                            <span class="text-muted">{{ __('Resumed from') }}</span>
                            <button type="button" class="link" wire:click="show({{ $shown->resumedFrom->id }})">#{{ $shown->resumedFrom->id }}</button>
                        </div>
                    @endif
                </div>

                @if (filled($shown->decision_reason))
                    <div class="rounded-xl border border-base-300 bg-base-200/40 p-3 text-sm">
                        <div class="mb-1 font-semibold">{{ __('Reason sent to the member') }}</div>
                        {{ $shown->decision_reason }}
                    </div>
                @endif

                <div class="space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Proofs') }}</p>
                    @forelse ($shown->files as $file)
                        @php
                            $fileUrl = route('admin.expense-reports.file', $file);
                        @endphp
                        <div wire:key="proof-{{ $file->id }}" class="overflow-hidden rounded-xl border border-base-300">
                            <div class="flex items-center justify-between gap-2 bg-base-200/40 px-3 py-2 text-sm">
                                <span class="truncate">{{ $file->original_name }}</span>
                                <span class="flex shrink-0 gap-1">
                                    <a class="btn btn-ghost btn-xs" href="{{ $fileUrl }}" target="_blank">{{ __('Open') }}</a>
                                    <a class="btn btn-ghost btn-xs" href="{{ $fileUrl }}?download=1">{{ __('Download') }}</a>
                                </span>
                            </div>
                            @if ($file->isImage())
                                <img src="{{ $fileUrl }}" alt="{{ $file->original_name }}" class="max-h-[32rem] w-full bg-base-200 object-contain" loading="lazy" />
                            @elseif ($file->isPdf())
                                <iframe src="{{ $fileUrl }}" title="{{ $file->original_name }}" class="h-[32rem] w-full bg-base-200"></iframe>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-muted">{{ __('The proofs were purged.') }}</p>
                    @endforelse
                </div>
            </div>

            <x-slot:actions>
                @can('cancelAcceptance', $shown)
                    <x-button :label="__('Undo the acceptance')" icon="o-arrow-uturn-left" class="btn-ghost"
                        wire:click="cancelAcceptance"
                        wire:confirm="{{ __('Cancel the refund and send this report back to be decided?') }}"
                        spinner="cancelAcceptance" />
                @endcan
                @can('decide', $shown)
                    <x-button :label="__('Reject')" icon="o-x-mark" class="btn-error btn-outline" wire:click="openReject" />
                    <x-button :label="__('Accept')" icon="o-check" class="btn-primary" wire:click="openAccept" />
                @endcan
            </x-slot:actions>
        @endif
    </x-drawer>

    {{-- Accept --}}
    <x-app-modal wire:model="acceptModal" :title="__('Accept this expense report')" separator :open="$acceptModal">
        <div class="space-y-3">
            <p class="text-sm text-base-content/80">
                {{ __('A refund is opened to the member\'s account. You may accept less than declared — never more — and then say why.') }}
            </p>
            <x-input wire:model.live.blur="acceptedAmount" :label="__('Amount to refund')" inputmode="decimal" suffix="€" />
            <x-textarea wire:model="decisionReason" :label="__('Reason for a lower amount')" rows="2"
                :hint="__('Sent to the member. Required only when the amount is lower than declared.')" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('acceptModal', false)" />
            <x-button :label="__('Accept')" class="btn-primary" wire:click="confirmAccept" spinner="confirmAccept" />
        </x-slot:actions>
    </x-app-modal>

    {{-- Reject --}}
    <x-app-modal wire:model="rejectModal" :title="__('Reject this expense report')" separator :open="$rejectModal">
        <div class="space-y-3">
            <p class="text-sm text-base-content/80">
                {{ __('Final: the member can resume it into a new report if they can fix it.') }}
            </p>
            <x-textarea wire:model="decisionReason" :label="__('Reason')" rows="3"
                :placeholder="__('e.g. the receipt is unreadable, the proof of payment is missing')"
                :hint="__('Sent to the member as is.')" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('rejectModal', false)" />
            <x-button :label="__('Reject')" class="btn-error" wire:click="confirmReject" spinner="confirmReject" />
        </x-slot:actions>
    </x-app-modal>
</div>
