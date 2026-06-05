<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="Contacts">
        <x-slot:middle>
            <x-input class="w-full" clearable icon="o-magnifying-glass"
                :placeholder="__('Search by name, email…')"
                wire:model.live.debounce.300ms="search" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button class="btn-ghost {{ count($filterChips) > 0 ? 'btn-active' : '' }}"
                icon="o-funnel"
                :label="__('Filters')"
                wire:click="$set('filterDrawer', true)">
                @if (count($filterChips) > 0)
                    <x-badge class="badge-sm badge-primary" value="{{ count($filterChips) }}" />
                @endif
            </x-button>
            {{-- Mobile selection toggle --}}
            <x-button
                class="btn-ghost btn-sm lg:hidden {{ $selectionModeActive ? 'btn-active' : '' }}"
                icon="{{ $selectionModeActive ? 'o-x-mark' : 'o-check-circle' }}"
                :label="$selectionModeActive ? __('Cancel') : __('Select')"
                wire:click="toggleSelectionMode" />
        </x-slot:actions>
    </x-header>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => __('New'),       'key' => 'totalNew',       'bg' => 'bg-info/10',    'color' => 'text-info'],
                ['label' => __('Pending'),   'key' => 'totalPending',   'bg' => 'bg-warning/10', 'color' => 'text-warning'],
                ['label' => __('Processed'), 'key' => 'totalProcessed', 'bg' => 'bg-success/10', 'color' => 'text-success'],
                ['label' => __('Rejected'),  'key' => 'totalRejected',  'bg' => 'bg-error/10',   'color' => 'text-error'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-card class="shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <x-icon name="o-envelope" class="h-5 w-5 {{ $card['color'] }}" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold {{ $card['color'] }}">{{ $stats[$card['key']] ?? 0 }}</p>
                        <p class="text-xs text-base-content/40">{{ $card['label'] }}</p>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- ── Vue mobile ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($contacts as $contact)
            @php
                $badgeClass = match ($contact->status) {
                    'new'       => 'badge-info badge-soft',
                    'pending'   => 'badge-warning badge-soft',
                    'processed' => 'badge-success badge-soft',
                    'rejected'  => 'badge-error badge-soft',
                    default     => 'badge-ghost',
                };
                $statusLabel = match ($contact->status) {
                    'new'       => __('New'),
                    'pending'   => __('Pending'),
                    'processed' => __('Processed'),
                    'rejected'  => __('Rejected'),
                    default     => $contact->status,
                };
            @endphp
            <x-list-item :item="$contact" class="bg-base-100 rounded-lg border"
                wire:key="mobile-contact-{{ $contact->id }}"
                @click.prevent="$wire.selectionModeActive || $wire.openDetail({{ $contact->id }})">
                <x-slot:avatar>
                    @if ($selectionModeActive)
                        <input type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                            value="{{ $contact->id }}"
                            wire:model.live="selected" />
                    @endif
                </x-slot:avatar>
                <x-slot:value>
                    <span class="font-medium">{{ $contact->first_name }} {{ $contact->last_name }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex items-center gap-2">
                        <x-badge :value="$statusLabel" class="{{ $badgeClass }} badge-sm" />
                        <span class="text-xs text-base-content/40">{{ $contact->email }}</span>
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    @if (! $selectionModeActive)
                        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                            :tooltip="__('Delete')"
                            wire:click.stop="confirmDelete({{ $contact->id }})" />
                    @endif
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-envelope"
                :heading="__('No contacts found')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- ── Vue desktop ────────────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($contacts->isEmpty())
                <x-empty-state
                    icon="o-envelope"
                    :heading="__('No contacts found')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$contacts" :sort-by="$sortBy"
                    selectable wire:model.live="selected">
                    @scope('cell_full_name', $contact)
                        <span class="font-medium">
                            {{ $contact->first_name }} {{ $contact->last_name }}
                        </span>
                    @endscope
                    @scope('cell_email', $contact)
                        <span class="text-sm text-base-content/60">{{ $contact->email }}</span>
                    @endscope
                    @scope('cell_interest', $contact)
                        @if ($contact->interest)
                            <x-badge :value="$contact->interest->getLabel()" class="badge-ghost badge-sm" />
                        @endif
                    @endscope
                    @scope('cell_status', $contact)
                        @php
                            $badgeClass = match ($contact->status) {
                                'new'       => 'badge-info badge-soft',
                                'pending'   => 'badge-warning badge-soft',
                                'processed' => 'badge-success badge-soft',
                                'rejected'  => 'badge-error badge-soft',
                                default     => 'badge-ghost',
                            };
                            $statusLabel = match ($contact->status) {
                                'new'       => __('New'),
                                'pending'   => __('Pending'),
                                'processed' => __('Processed'),
                                'rejected'  => __('Rejected'),
                                default     => $contact->status,
                            };
                        @endphp
                        <x-badge :value="$statusLabel" class="{{ $badgeClass }}" />
                    @endscope
                    @scope('cell_created_at', $contact)
                        <span class="text-xs text-base-content/40">
                            {{ $contact->created_at->translatedFormat('d M Y') }}
                        </span>
                    @endscope
                    @scope('actions', $contact)
                        <x-admin.shared.row-actions>
                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-eye"
                                :tooltip="__('View detail')"
                                wire:click="openDetail({{ $contact->id }})" />
                            <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-trash"
                                :tooltip="__('Delete')"
                                wire:click="confirmDelete({{ $contact->id }})" />
                        </x-admin.shared.row-actions>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $contacts->links() }}
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Floating Pill — bulk actions ───────────────────────────────── --}}
    <x-admin.shared.selection-pill
        :selected="$selected"
        :total="$this->getTotalMatchingCount()"
        :selecting-all-results="$selectingAllResults"
        :select-all="$selectAll">
        <x-slot:actions>
            <x-button class="btn-ghost btn-sm text-error" icon="o-trash" :label="__('Delete')"
                wire:click="confirmBulkDelete" />
        </x-slot:actions>
    </x-admin.shared.selection-pill>

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Interest') }}
                </p>
                <x-select :options="$interestOptions" :placeholder="__('All interests')"
                    wire:model.live="interest" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Status') }}
                </p>
                <x-select :options="$statusOptions" :placeholder="__('All statuses')"
                    wire:model.live="status" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Drawer détail contact ─────────────────────────────────────── --}}
    <x-drawer wire:model="detailOpen" :title="__('Contact detail')" right class="w-full max-w-md">
        @if ($selectedContact)
            <div class="space-y-5 p-1">

                <div class="bg-base-200 space-y-2 rounded-lg p-4">
                    <p class="text-lg font-bold">
                        {{ $selectedContact->first_name }} {{ $selectedContact->last_name }}
                    </p>
                    <p class="text-sm text-base-content/60">{{ $selectedContact->email }}</p>
                    @if ($selectedContact->phone)
                        <p class="text-sm text-base-content/60">{{ $selectedContact->phone }}</p>
                    @endif
                    @if ($selectedContact->interest)
                        <x-badge :value="$selectedContact->interest->getLabel()" class="badge-soft badge-primary" />
                    @endif
                </div>

                @if ($selectedContact->message)
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-widest opacity-50">
                            {{ __('Message') }}
                        </p>
                        <p class="text-sm leading-relaxed">{{ $selectedContact->message }}</p>
                    </div>
                @endif

                @if ($selectedContact->membership_total_cost)
                    <div class="border-info/30 bg-info/5 space-y-1 rounded-lg border p-3">
                        <p class="text-info text-xs font-semibold uppercase tracking-widest">
                            {{ __('Membership request') }}
                        </p>
                        <p class="text-sm">{{ __('Family members') }}: {{ $selectedContact->membership_family_members ?? '—' }}</p>
                        <p class="text-sm">{{ __('Competitors') }}: {{ $selectedContact->membership_competitors ?? '—' }}</p>
                        <p class="text-sm">{{ __('Training sessions/week') }}: {{ $selectedContact->membership_training_sessions ?? '—' }}</p>
                        <p class="text-sm font-semibold">{{ __('Estimated cost') }}: {{ $selectedContact->membership_total_cost }} €</p>
                    </div>
                @endif

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                        {{ __('Status') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ([['new', __('New'), 'btn-info'], ['pending', __('Pending'), 'btn-warning'], ['processed', __('Processed'), 'btn-success'], ['rejected', __('Rejected'), 'btn-error']] as [$val, $label, $cls])
                            <x-button class="btn-sm btn-soft {{ $cls }} {{ $selectedContact->status === $val ? 'opacity-100' : 'opacity-40' }}"
                                :label="$label"
                                wire:click="updateStatus({{ $selectedContact->id }}, '{{ $val }}')" />
                        @endforeach
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                        {{ __('Send an email') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <x-button class="btn-sm btn-outline" :label="__('Welcome')"
                            wire:click="sendTemplateEmail('welcome')" />
                        <x-button class="btn-sm btn-outline" :label="__('Membership info')"
                            wire:click="sendTemplateEmail('membership_info')" />
                        <x-button class="btn-sm btn-outline" :label="__('Info request')"
                            wire:click="sendTemplateEmail('request_info')" />
                        <x-button class="btn-sm btn-outline btn-error" :label="__('Polite decline')"
                            wire:click="sendTemplateEmail('polite_decline')" />
                    </div>
                    <x-button class="btn-ghost btn-sm mt-2 w-full" icon="o-pencil-square"
                        :label="__('Custom email…')"
                        wire:click="$set('emailModal', true)" />
                </div>

                @if (in_array($selectedContact->interest?->value, ['JOIN_US', 'TRIAL']) && $selectedContact->status !== 'processed')
                    <div class="border-base-200 border-t pt-3">
                        <x-button class="btn-primary btn-sm w-full" icon="o-user-plus"
                            :label="__('Onboard as member')"
                            wire:click="onboardContact({{ $selectedContact->id }})"
                            spinner />
                    </div>
                @endif

                <div class="border-base-200 border-t pt-2">
                    <x-button class="btn-ghost btn-sm w-full text-error" icon="o-trash"
                        :label="__('Delete this contact')"
                        wire:click="confirmDelete({{ $selectedContact->id }})" />
                </div>
            </div>
        @endif
    </x-drawer>

    {{-- ── Modal email personnalisé ──────────────────────────────────── --}}
    <x-modal wire:model="emailModal" :title="__('Custom email')">
        <div class="space-y-4">
            <x-input :label="__('Subject')" wire:model="emailSubject" />
            <x-textarea :label="__('Message')" wire:model="emailBody" rows="6" />
            <div class="flex items-center gap-2">
                <x-toggle wire:model="emailCopy" />
                <span class="text-sm">{{ __('Receive a copy') }}</span>
            </div>
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('emailModal', false)" />
            <x-button class="btn-primary" icon="o-paper-airplane" :label="__('Send')"
                wire:click="sendCustomEmail" spinner />
        </x-slot:actions>
    </x-modal>

    {{-- ── Modal suppression unitaire ───────────────────────────────── --}}
    <x-confirm-modal model="deleteModal" :title="__('Delete this contact?')"
        :confirmLabel="__('Delete')" confirmAction="delete">
        <p>{{ __('This action is irreversible.') }}</p>
    </x-confirm-modal>

    {{-- ── Modal suppression bulk ───────────────────────────────────── --}}
    <x-confirm-modal model="confirmBulkDeleteModal" :title="__('Delete selected contacts?')"
        :confirmLabel="__('Delete')" confirmAction="bulkDelete">
        <p>
            {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            {{ __('will be permanently deleted.') }}
        </p>
    </x-confirm-modal>
</div>
