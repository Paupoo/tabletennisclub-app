<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('Cash Register')" :subtitle="__('In-person cash management')" separator progress-indicator>
        <x-slot:actions>
            @can('cash_register.holder.change')
            <x-button
                :label="__('New register')"
                icon="o-building-library"
                class="btn-outline btn-sm"
                wire:click="$set('createRegisterModal', true)" />
            @endcan
            @if($this->register)
            @can('cash_register.entry.create')
            <x-button
                :label="__('Add entry')"
                icon="o-plus"
                class="btn-primary btn-sm"
                wire:click="openManualEntry" />
            @endcan
            @can('cash_register.manage')
            @unless($this->register->trashed())
            <x-button
                :label="__('Retire')"
                icon="o-archive-box-x-mark"
                class="btn-ghost btn-sm text-error"
                wire:click="openRetireRegister" />
            @endunless
            @endcan
            @endif
        </x-slot:actions>
    </x-header>

    @if($this->registers->isEmpty())
    <div class="flex flex-col items-center justify-center py-20 text-muted">
        <x-icon name="o-currency-euro" class="w-16 h-16 mb-4" />
        <p class="text-sm italic">{{ __('No cash register yet. Create one to get started.') }}</p>
    </div>
    @else

    {{-- Register selector (if multiple) --}}
    @if($this->registers->count() > 1 || $showRetired)
    <div class="mb-6 flex flex-wrap items-center gap-2">
        @foreach($this->registers as $reg)
        <x-button
            :label="$reg->trashed() ? $reg->name . ' — ' . __('Retired') : $reg->name"
            wire:click="$set('selectedRegisterId', {{ $reg->id }})"
            @class(['btn-sm', 'btn-primary' => $selectedRegisterId === $reg->id, 'btn-outline' => $selectedRegisterId !== $reg->id, 'opacity-60' => $reg->trashed()]) />
        @if($reg->trashed())
        @can('cash_register.manage')
        <x-button
            :label="__('Put back in service')"
            icon="o-arrow-path"
            class="btn-ghost btn-sm"
            wire:click="restoreRegister({{ $reg->id }})" />
        @endcan
        @endif
        @endforeach
    </div>
    @endif

    @if($this->register)
    {{-- Holder info --}}
    <div class="flex items-center gap-3 mb-4 px-1">
        <x-icon name="o-user-circle" class="w-5 h-5 text-base-content/40 shrink-0" />
        <span class="text-sm text-base-content/60">{{ __('Holder') }}:</span>
        @if($this->register->heldBy)
            <span class="text-sm font-medium">{{ $this->register->heldBy->first_name }} {{ $this->register->heldBy->last_name }}</span>
        @else
            <span class="text-sm italic text-base-content/40">{{ __('None') }}</span>
        @endif
        @can('cash_register.holder.change')
            <x-button
                :label="__('Change')"
                icon="o-pencil"
                class="btn-ghost btn-xs ml-1"
                wire:click="openChangeHolder" />
        @endcan
    </div>

    {{-- Balance card --}}
    @php
        $entriesIn = $this->register->entries->where('amount', '>', 0);
        $entriesOut = $this->register->entries->where('amount', '<', 0);
    @endphp
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-3">
        <x-admin.shared.stat-card
            :label="__('Current balance')"
            :value="number_format($this->balance / 100, 2, ',', ' ') . ' €'"
            :hint="$this->register->name"
            icon="o-currency-euro"
            :color="$this->balance >= 0 ? 'success' : 'error'"
            emphasis
            class="sm:col-span-2 lg:col-span-1" />

        <x-admin.shared.stat-card
            :label="__('Total in')"
            :value="number_format($entriesIn->sum('amount') / 100, 2, ',', ' ') . ' €'"
            :hint="$entriesIn->count() . ' ' . __('entries')"
            icon="o-arrow-down-tray"
            color="success" />

        <x-admin.shared.stat-card
            :label="__('Total out')"
            :value="number_format(abs($entriesOut->sum('amount')) / 100, 2, ',', ' ') . ' €'"
            :hint="$entriesOut->count() . ' ' . __('entries')"
            icon="o-arrow-up-tray"
            color="error" />
    </div>

    {{-- Entries history --}}
    <x-card class="bg-base-100 shadow-sm">
        <div class="text-xs font-bold uppercase tracking-widest text-muted mb-4">{{ __('History') }}</div>

        @forelse($this->register->entries->sortByDesc('created_at') as $entry)
        <div class="flex items-center gap-4 p-3 rounded-xl border border-base-300 mb-2">
            <div @class([
                'w-8 h-8 rounded-full flex items-center justify-center shrink-0',
                'bg-success/15' => $entry->amount > 0,
                'bg-error/15'   => $entry->amount < 0,
            ])>
                <x-icon
                    :name="$entry->amount > 0 ? 'o-arrow-down-tray' : 'o-arrow-up-tray'"
                    @class(['w-4 h-4', 'text-success' => $entry->amount > 0, 'text-error' => $entry->amount < 0]) />
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-sm">
                        {{ match($entry->reason) {
                            'tournament_payment' => __('Tournament payment'),
                            'training_payment'   => __('Training payment'),
                            default              => __('Manual entry'),
                        } }}
                    </span>
                    @if($entry->payable_type)
                    <x-badge value="{{ class_basename($entry->payable_type) }}" class="badge-ghost badge-sm" />
                    @elseif($entry->isInternal())
                    {{-- De la caisse à la banque, ou l'inverse : ni recette ni dépense. --}}
                    <x-badge value="{{ __('Bank deposit') }}" class="badge-neutral badge-soft badge-sm" />
                    @elseif($entry->isJustified())
                    <x-badge value="{{ __('Justified') }}" class="badge-success badge-soft badge-sm" />
                    @endif
                </div>
                @if($entry->notes)
                <div class="text-xs opacity-60 mt-0.5 truncate">{{ $entry->notes }}</div>
                @endif
                <div class="text-xs text-muted mt-0.5">
                    {{ $entry->recordedBy?->first_name }} {{ $entry->recordedBy?->last_name }}
                    · {{ $entry->created_at->format('d/m/Y H:i') }}
                </div>
            </div>
            <div @class([
                'tabular-nums font-black text-right shrink-0',
                'text-success' => $entry->amount > 0,
                'text-error'   => $entry->amount < 0,
            ])>
                {{ $entry->amount > 0 ? '+' : '' }}{{ number_format($entry->amount / 100, 2, ',', ' ') }} €
            </div>
            {{-- Hors site : une pièce justifie le mouvement, ou c'est un
                 versement de/vers la banque. Jamais les deux, jamais sur un
                 paiement que le site compte déjà. --}}
            @if($entry->payable_type === null)
            @php
                $canDocument = auth()->user()->can('create', \App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::class);
                $canDeposit = auth()->user()->can('linkCashDeposit', \App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::class);
            @endphp
            <div class="shrink-0">
                @if($entry->isInternal())
                    @if($canDeposit)
                    <x-button :label="__('Unlink from the bank')" class="btn-ghost btn-xs"
                        wire:click="unlinkDeposit({{ $entry->id }})" spinner="unlinkDeposit({{ $entry->id }})" />
                    @endif
                @elseif($entry->isJustified())
                    <x-button :label="__('Documents')" icon="o-document-check" class="btn-ghost btn-xs"
                        wire:click="openEntryDocuments({{ $entry->id }})" />
                @elseif($canDocument && $canDeposit)
                    <x-admin.shared.row-menu :label="__('Justify')" icon="o-document-check"
                        :wire-click="'openEntryDocuments(' . $entry->id . ')'">
                        <x-menu-item icon="o-building-library" :title="__('It is a deposit to or from the bank')" wire:click="openDeposit({{ $entry->id }})" />
                    </x-admin.shared.row-menu>
                @elseif($canDocument)
                    <x-button :label="__('Justify')" icon="o-document-check" class="btn-outline btn-xs"
                        wire:click="openEntryDocuments({{ $entry->id }})" />
                @endif
            </div>
            @endif
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-10 text-muted">
            <x-icon name="o-inbox" class="w-10 h-10 mb-3" />
            <p class="text-sm italic">{{ __('No entries yet.') }}</p>
        </div>
        @endforelse
    </x-card>
    @endif
    @endif

    @can('cash_register.manage')
    <div class="mb-4 flex justify-end">
        <x-checkbox :label="__('Show retired registers')" wire:model.live="showRetired" class="text-sm" />
    </div>
    @endcan

    {{-- Modal: Retire register --}}
    <x-app-modal wire:model="retireRegisterModal" :title="__('Retire this cash register')" separator
        :open="$retireRegisterModal">
        <p class="text-sm">
            {{ __('It leaves the list but keeps every movement it recorded. You can put it back in service later.') }}
        </p>
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.retireRegisterModal = false" class="btn-ghost" />
            <x-button :label="__('Retire')" icon="o-archive-box-x-mark" class="btn-error" wire:click="retireRegister"
                spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Modal: Create register --}}
    <x-app-modal wire:model="createRegisterModal" :title="__('Create Cash Register')" separator :open="$createRegisterModal">
        <div class="space-y-4">
            <x-input :label="__('Register name')" wire:model="newRegisterName" autofocus />
            <x-choices-offline
                :label="__('Holder')"
                :options="$users"
                :placeholder="__('Search for a member...')"
                wire:model="newRegisterHolderUserId"
                single searchable clearable />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.createRegisterModal = false" class="btn-ghost" />
            <x-button :label="__('Create')" icon="o-check" class="btn-primary" wire:click="createRegister" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Modal: Change holder --}}
    <x-app-modal wire:model="changeHolderModal" :title="__('Change holder')" separator :open="$changeHolderModal">
        <x-choices-offline
            :label="__('Holder')"
            :options="$users"
            :placeholder="__('Search for a member...')"
            wire:model="newHolderUserId"
            single searchable clearable />
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.changeHolderModal = false" class="btn-ghost" />
            <x-button :label="__('Save')" icon="o-check" class="btn-primary" wire:click="confirmChangeHolder" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Modal: Manual entry --}}
    <x-app-modal wire:model="manualEntryModal" :title="__('Add Entry')" separator :open="$manualEntryModal">
        <div class="space-y-4">
            <p class="text-sm opacity-60">
                {{ __('Use a positive amount for cash in, negative for cash out.') }}
            </p>
            <x-input
                :label="__('Amount (€)')"
                wire:model="entryAmount"
                type="number"
                :hint="__('Positive = cash in, negative = cash out')" />
            <x-select
                :label="__('Reason')"
                :options="$reasonOptions"
                option-label="name"
                wire:model="entryReason" />
            <x-textarea
                :label="__('Notes')"
                wire:model="entryNotes"
                rows="2"
                :placeholder="__('Optional notes...')" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.manualEntryModal = false" class="btn-ghost" />
            <x-button :label="__('Save')" icon="o-check" class="btn-primary" wire:click="saveManualEntry" spinner />
        </x-slot:actions>
    </x-app-modal>
    {{-- Les pièces d'un mouvement de caisse --}}
    <x-drawer wire:model="entryDocumentsDrawer" :title="__('Supporting documents of this movement')" right with-close-button class="w-full max-w-2xl">
        @if($this->documentEntry)
            @php
                $docEntry = $this->documentEntry;
                $canFile = auth()->user()->can('create', \App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::class)
                    && $docEntry->payable_type === null && ! $docEntry->isInternal();
            @endphp
            <div class="space-y-5" wire:key="entry-documents-{{ $docEntry->id }}">
                <div class="rounded-lg border border-base-300 bg-base-200/60 p-3 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                        <span class="font-semibold">{{ $docEntry->reason }}</span>
                        <span @class(['font-black tabular-nums', 'text-success' => $docEntry->amount > 0, 'text-error' => $docEntry->amount < 0])>{{ number_format($docEntry->amount / 100, 2, ',', ' ') }} €</span>
                    </div>
                    <div class="text-xs text-muted">{{ $docEntry->created_at?->format('d/m/Y H:i') }}</div>
                </div>

                @if($docEntry->supportingDocuments->isNotEmpty())
                    <div class="space-y-2">
                        <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Justified by') }}</p>
                        @foreach($docEntry->supportingDocuments as $linked)
                            <div wire:key="entry-linked-{{ $linked->id }}" class="flex items-center gap-3 rounded-lg border border-success/20 bg-success/5 p-2.5 text-sm">
                                <x-icon name="o-document-check" class="h-4 w-4 shrink-0 text-success" />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold">{{ $linked->counterparty }} — {{ $linked->label }}</div>
                                    <div class="text-xs text-muted"><span class="font-mono">{{ $linked->reference() }}</span> · {{ $linked->category()->label() }} · {{ number_format($linked->amount, 2, ',', ' ') }} €</div>
                                </div>
                                @foreach($linked->files as $file)
                                    <a class="btn btn-ghost btn-xs" href="{{ route('admin.treasury.supporting-documents.file', $file) }}" target="_blank">{{ __('Open it') }}</a>
                                    @break
                                @endforeach
                                @can('linkCashRegisterEntry', $linked)
                                    <x-button :label="__('Unlink')" class="btn-ghost btn-xs" wire:click="unlinkEntryDocument({{ $linked->id }})" spinner="unlinkEntryDocument({{ $linked->id }})" />
                                @endcan
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($canFile)
                    <div class="space-y-2" data-entry-document-suggestions>
                        <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Link an existing document') }}</p>
                        @foreach($this->entryDocumentSuggestions as $candidate)
                            <div wire:key="entry-suggested-{{ $candidate->id }}" class="flex items-center gap-3 rounded-lg border border-success/30 bg-success/5 p-2.5 text-sm">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold">{{ $candidate->counterparty }} — {{ $candidate->label }}</div>
                                    <div class="text-xs text-muted"><span class="font-mono">{{ $candidate->reference() }}</span> · {{ $candidate->date->format('d/m/Y') }}</div>
                                </div>
                                <span class="shrink-0 font-bold tabular-nums">{{ number_format($candidate->amount, 2, ',', ' ') }} €</span>
                                <x-button :label="__('Link')" icon="o-link" class="btn-outline btn-xs" wire:click="linkEntryDocument({{ $candidate->id }})" spinner="linkEntryDocument({{ $candidate->id }})" />
                            </div>
                        @endforeach
                        <x-input :placeholder="__('Search a counterparty, a label, a reference...')" icon="o-magnifying-glass"
                            wire:model.live.debounce.300ms="entryDocumentSearch" clearable />
                        @foreach($this->entryDocumentSearchResults as $candidate)
                            <div wire:key="entry-found-{{ $candidate->id }}" class="flex items-center gap-3 rounded-lg border border-base-300 p-2.5 text-sm">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate font-semibold">{{ $candidate->counterparty }} — {{ $candidate->label }}</div>
                                    <div class="text-xs text-muted"><span class="font-mono">{{ $candidate->reference() }}</span> · {{ $candidate->date->format('d/m/Y') }} · {{ $candidate->state()->label() }}</div>
                                </div>
                                <span class="shrink-0 font-bold tabular-nums">{{ number_format($candidate->amount, 2, ',', ' ') }} €</span>
                                <x-button :label="__('Link')" icon="o-link" class="btn-ghost btn-xs" wire:click="linkEntryDocument({{ $candidate->id }})" spinner="linkEntryDocument({{ $candidate->id }})" />
                            </div>
                        @endforeach
                    </div>

                    <div class="space-y-3 rounded-xl border border-base-300 p-3" data-new-entry-document>
                        <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Or file a new document') }}</p>
                        <x-admin.treasury.supporting-document-form :files="$documentFiles" />
                        <div class="flex justify-end">
                            <x-button :label="__('File and link')" icon="o-document-plus" class="btn-primary btn-sm"
                                wire:click="createAndLinkEntryDocument" spinner="createAndLinkEntryDocument" />
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </x-drawer>

    {{-- Versement de/vers la banque --}}
    <x-drawer wire:model="depositDrawer" :title="__('A deposit to or from the bank')" right with-close-button class="w-full max-w-2xl">
        @if($this->documentEntry)
            @php
                $depositEntry = $this->documentEntry;
            @endphp
            <div class="space-y-4" wire:key="deposit-{{ $depositEntry->id }}">
                <p class="text-sm">
                    {{ $depositEntry->amount < 0
                        ? __('The till gave :amount € to the bank. Pick the credit it became on the statement: both movements become internal, neither income nor expense.', ['amount' => number_format(abs($depositEntry->amount) / 100, 2, ',', ' ')])
                        : __('The till received :amount € from the bank. Pick the withdrawal on the statement: both movements become internal, neither income nor expense.', ['amount' => number_format(abs($depositEntry->amount) / 100, 2, ',', ' ')]) }}
                </p>
                @forelse($this->depositSuggestions as $line)
                    <div wire:key="deposit-line-{{ $line->id }}" class="flex items-center gap-3 rounded-lg border border-base-300 p-2.5 text-sm">
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold">{{ $line->counterparty_name ?: $line->description }}</div>
                            <div class="text-xs text-muted">{{ $line->date->format('d/m/Y') }} · {{ \Illuminate\Support\Str::limit($line->description, 60) }}</div>
                        </div>
                        <span class="shrink-0 font-bold tabular-nums">{{ number_format($line->amount, 2, ',', ' ') }} €</span>
                        <x-button :label="__('Link')" icon="o-link" class="btn-outline btn-xs" wire:click="linkDeposit({{ $line->id }})" spinner="linkDeposit({{ $line->id }})" />
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('No bank line of this amount the other way round within :days days. Import the statement first.', ['days' => \App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument::SUGGESTION_WINDOW_DAYS]) }}</p>
                @endforelse
            </div>
        @endif
    </x-drawer>
</div>
