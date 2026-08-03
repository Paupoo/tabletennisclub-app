<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Users')">
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass" :placeholder="__('Search...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" />
            {{-- Desktop: full buttons --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @canany(['setOrUpdateForceList', 'create'], \App\Domains\ClubAdmin\Users\Models\User::class)
                    <x-dropdown :label="__('More actions')" icon="o-ellipsis-vertical" right class="btn-ghost btn-sm">
                        @can('users.import')
                            <x-menu-item icon="o-arrow-up-tray" :title="__('Import the federation listing')"
                                link="{{ route('admin.users.import') }}" />
                        @endcan
                        @can('setOrUpdateForceList', \App\Domains\ClubAdmin\Users\Models\User::class)
                            <x-menu-item icon="o-arrow-path" :title="__('Recalculate force list')"
                                wire:click="recalculateForceList" spinner="recalculateForceList" />
                        @endcan
                        @can('create', \App\Domains\ClubAdmin\Users\Models\User::class)
                            <x-menu-item icon="o-envelope" :title="__('Quick invite')"
                                wire:click="$set('quickInviteDrawer', true)" />
                        @endcan
                    </x-dropdown>
                @endcanany
                @can('create', \App\Domains\ClubAdmin\Users\Models\User::class)
                    <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('Create')"
                        link="{{ route('admin.users.create') }}" />
                @endcan
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="lg:hidden border-b border-base-200" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search...') }}" />
            </div>
            <button @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Cartes stats ──────────────────────────────────────────────── --}}
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @php
            $statCards = [
                ['label' => __('Total'),        'key' => 'total',        'icon' => 'o-users',        'bg' => 'bg-base-200',    'color' => 'text-base-content/60'],
                ['label' => __('Registered'),   'key' => 'registered',   'icon' => 'o-check-circle', 'bg' => 'bg-success/10',  'color' => 'text-success'],
                ['label' => __('Competitive'),  'key' => 'competitive',  'icon' => 'o-trophy',       'bg' => 'bg-primary/10',  'color' => 'text-primary'],
                ['label' => __('Unregistered'), 'key' => 'unregistered', 'icon' => 'o-x-circle',     'bg' => 'bg-base-200',    'color' => 'text-base-content/30'],
            ];
        @endphp
        @foreach ($statCards as $card)
            <x-card class="shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $card['bg'] }}">
                        <x-icon name="{{ $card['icon'] }}" class="h-5 w-5 {{ $card['color'] }}" />
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
        @forelse ($users as $user)
            @php
                $invStatus = $user->invitationStatus();
                $invBadge = match($invStatus) {
                    'active'     => ['label' => __('Account created'),     'class' => 'badge-success badge-soft badge-xs'],
                    'pending'    => ['label' => __('Pending'),    'class' => 'badge-warning badge-soft badge-xs'],
                    'expired'    => ['label' => __('Expired'),    'class' => 'badge-error badge-soft badge-xs'],
                    default      => ['label' => __('Not invited'),'class' => 'badge-ghost badge-xs'],
                };
            @endphp
            <x-list-item :item="$user" class="bg-base-100 rounded-lg border"
                wire:key="mobile-user-{{ $user->id }}">
                <x-slot:avatar>
                    @if ($selectionModeActive)
                        <input type="checkbox"
                            class="checkbox checkbox-primary checkbox-sm"
                            value="{{ $user->id }}"
                            wire:model.live="selected" />
                    @else
                        <x-avatar :image="$user->photo ?? '/images/empty-user.jpg'" class="w-10!" />
                    @endif
                </x-slot:avatar>
                <x-slot:value>
                    <span class="font-medium">{{ $user->first_name }} {{ $user->last_name }}</span>
                </x-slot:value>
                <x-slot:sub-value>
                    <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
                        @if ($user->is_competitor)
                            <x-badge :value="__('Competitive')" class="badge-primary badge-soft badge-sm" />
                        @else
                            <x-badge :value="__('Recreational')" class="badge-ghost badge-sm" />
                        @endif
                        <x-badge :value="$invBadge['label']" class="{{ $invBadge['class'] }}" />
                        @if ($user->has_paid)
                            <x-badge :value="__('Paid')" class="badge-success badge-soft badge-xs" />
                        @else
                            <x-badge :value="__('Unpaid')" class="badge-error badge-soft badge-xs" />
                        @endif
                        <span class="text-xs text-base-content/40">{{ $user->email }}</span>
                    </div>
                </x-slot:sub-value>
                <x-slot:actions>
                    @if (! $selectionModeActive)
                        <x-admin.shared.row-actions>
                            @can('update', $user)
                                <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                    :tooltip="__('Edit')" link="{{ route('admin.users.edit', $user) }}" />
                            @endcan
                            @if ($invStatus !== 'active')
                                @can('sendEmail', \App\Domains\ClubAdmin\Users\Models\User::class)
                                    {{-- An invitation hands over a login, so it only goes to the
                                         member's own address: sent to a guardian, it would set a
                                         password on somebody else's account. --}}
                                    @if ($user->email === null)
                                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-envelope" disabled
                                            :tooltip="__('This member has no address of their own yet, so they cannot be invited.')" />
                                    @else
                                        <x-button class="btn-ghost btn-sm btn-circle" icon="o-envelope"
                                            :tooltip="__('Resend invitation')"
                                            wire:click="sendInvitation({{ $user->id }})" spinner />
                                    @endif
                                @endcan
                            @endif
                            @can('delete', $user)
                                <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-archive-box"
                                    :tooltip="__('Archive')" wire:click="confirmDelete({{ $user->id }})" />
                            @endcan
                        </x-admin.shared.row-actions>
                    @endif
                </x-slot:actions>
            </x-list-item>
        @empty
            <x-empty-state
                icon="o-users"
                :heading="$showArchived ? __('No archived members') : __('No users found')"
                :message="__('Try adjusting your search or filters.')" />
        @endforelse
    </div>

    {{-- ── Vue desktop ────────────────────────────────────────────────── --}}
    <div class="hidden lg:block">
        <x-card>
            @if ($users->isEmpty())
                <x-empty-state
                    icon="o-users"
                    :heading="$showArchived ? __('No archived members') : __('No users found')"
                    :message="__('Try adjusting your search or filters.')" />
            @else
                <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy" selectable wire:model.live="selected">
                    @scope('cell_photo', $user)
                        <x-avatar class="h-10 w-10" image="{{ $user->photo ?? '/images/empty-user.jpg' }}" />
                    @endscope
                    @scope('cell_name', $user)
                        @can('update', $user)
                            <a class="font-medium hover:underline" href="{{ route('admin.users.edit', $user) }}">
                                {{ $user->first_name }} {{ $user->last_name }}
                            </a>
                        @else
                            <span class="font-medium">{{ $user->first_name }} {{ $user->last_name }}</span>
                        @endcan
                    @endscope
                    @scope('cell_is_competitive', $user)
                        @if ($user->is_competitor)
                            <x-badge :value="__('Competitive')" class="badge-primary badge-soft badge-sm" />
                        @else
                            <x-badge :value="__('Recreational')" class="badge-ghost badge-sm" />
                        @endif
                    @endscope
                    @scope('cell_ranking', $user)
                        <span class="text-sm font-mono">{{ $user->ranking ?? '—' }}</span>
                    @endscope
                    @scope('actions', $user)
                        @php
                            $invStatus = $user->invitationStatus();
                            $invBadge = match($invStatus) {
                                'active'  => ['label' => __('Account created'),     'class' => 'badge-success badge-soft badge-xs'],
                                'pending' => ['label' => __('Pending'),    'class' => 'badge-warning badge-soft badge-xs'],
                                'expired' => ['label' => __('Expired'),    'class' => 'badge-error badge-soft badge-xs'],
                                default   => ['label' => __('Not invited'),'class' => 'badge-ghost badge-xs'],
                            };
                        @endphp
                        <div class="flex items-center gap-2">
                            <x-badge :value="$invBadge['label']" class="{{ $invBadge['class'] }}" />
                            @if ($user->has_paid)
                                <x-badge :value="__('Paid')" class="badge-success badge-soft badge-xs" />
                            @else
                                <x-badge :value="__('Unpaid')" class="badge-error badge-soft badge-xs" />
                            @endif
                            <x-admin.shared.row-actions>
                                @can('update', $user)
                                    <x-button class="btn-ghost btn-sm btn-circle" icon="o-pencil"
                                        :tooltip="__('Edit')" link="{{ route('admin.users.edit', $user->id) }}" />
                                @endcan
                                @if ($invStatus !== 'active')
                                    @can('sendEmail', \App\Domains\ClubAdmin\Users\Models\User::class)
                                        {{-- Same rule as the mobile list: no address, no login to hand over. --}}
                                        @if ($user->email === null)
                                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-envelope" disabled
                                                :tooltip="__('This member has no address of their own yet, so they cannot be invited.')" />
                                        @else
                                            <x-button class="btn-ghost btn-sm btn-circle" icon="o-envelope"
                                                :tooltip="__('Resend invitation')"
                                                wire:click="sendInvitation({{ $user->id }})" spinner />
                                        @endif
                                    @endcan
                                @endif
                                @can('delete', $user)
                                    @if ($user->trashed())
                                        <x-button class="btn-ghost btn-sm btn-circle text-success" icon="o-arrow-path"
                                            :tooltip="__('Restore')"
                                            wire:click="restoreUser({{ $user->id }})" spinner />
                                    @else
                                        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-archive-box"
                                            :tooltip="__('Archive')" wire:click="confirmDelete({{ $user->id }})" />
                                        <x-button class="btn-ghost btn-sm btn-circle text-error" icon="o-no-symbol"
                                            :tooltip="__('Anonymize (GDPR)')"
                                            wire:click="openAnonymizeModal({{ $user->id }})" />
                                    @endif
                                @endcan
                            </x-admin.shared.row-actions>
                        </div>
                    @endscope
                </x-table>
                <div class="mt-4">
                    {{ $users->links() }}
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
            @can('users.update')
                <x-button class="btn-ghost btn-sm" icon="o-user-group" :label="__('Add to team')"
                    wire:click="$set('addToTeamModal', true)" />
            @endcan
            @can('subscriptions.manage')
                <x-button class="btn-ghost btn-sm" icon="o-calendar" :label="__('Subscribe')"
                    wire:click="$set('subscribeModal', true)" />
            @endcan
            @can('sendEmail', \App\Domains\ClubAdmin\Users\Models\User::class)
                <x-button class="btn-ghost btn-sm" icon="o-envelope" :label="__('Invite')"
                    wire:click="bulkInvite" spinner="bulkInvite" />
            @endcan
            <span class="text-base-content/20">|</span>
            @can('users.delete')
                <x-button class="btn-ghost btn-sm text-error" icon="o-archive-box" :label="__('Archive')"
                    wire:click="confirmBulkArchive" />
            @endcan
        </x-slot:actions>
    </x-admin.shared.selection-pill>

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Licence type') }}
                </p>
                <x-radio wire:model.live="selectedLicenceType" :options="$licenceTypes" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Gender') }}
                </p>
                <div class="space-y-1">
                    @foreach (\App\Domains\Shared\Enums\Gender::options() as $gender)
                        <x-checkbox :label="$gender['name']" :value="$gender['id']" wire:model.live="categories" />
                    @endforeach
                </div>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Account') }}
                </p>
                <x-radio wire:model.live="invitationState" :options="$invitationStates" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Profile') }}
                </p>
                <x-toggle :label="__('Incomplete profile')" wire:model.live="incompleteProfile" />
                <x-toggle class="mt-2" :label="__('Adult without an address')"
                    :hint="__('Grown members who cannot be invited yet — ask them for an address of their own.')"
                    wire:model.live="adultWithoutAddress" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Subscription') }}
                </p>
                <x-toggle :label="__('Unpaid subscription')" wire:model.live="unpaidSubscription" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Equipment') }}
                </p>
                <x-toggle :label="__('Has a key')" wire:model.live="hasKey" />
                <x-toggle :label="__('Has a cash register')" wire:model.live="hasCashRegister" class="mt-2" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Teams') }}
                </p>
                <x-choices :options="$teams" class="w-full" clearable :placeholder="__('Select a team...')"
                    wire:model.live="team_ids" />
            </div>
            @can('users.delete')
                <div class="border-t border-base-200 pt-4">
                    <x-toggle wire:model.live="showArchived" :label="__('Show archived members')" right />
                </div>
            @endcan
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Modales archivage (simple et en masse) ────────────────────── --}}
    @can('users.delete')
        <x-confirm-modal model="deleteModal" :title="__('Archive this member?')"
            :confirmLabel="__('Archive')" confirmAction="delete">
            <p>{{ __('The member will be archived and can be restored later. No data is permanently deleted.') }}</p>
        </x-confirm-modal>

        <x-confirm-modal model="confirmArchiveModal" :title="__('Archive selected members?')"
            :confirmLabel="__('Archive')" confirmAction="bulkArchive">
            <p>{{ __('Selected members will be archived. Your own account is automatically excluded. Members can be restored later.') }}</p>
        </x-confirm-modal>
    @endcan

    {{-- Sending again invalidates the link the member may be about to click --}}
    @can('sendEmail', \App\Domains\ClubAdmin\Users\Models\User::class)
        <x-confirm-modal model="confirmReinviteModal" :title="__('Send a second invitation?')"
            :confirmLabel="__('Send again')" confirmAction="confirmBulkInvite">
            <p>{{ __(':count selected member(s) were already invited and have not accepted yet.', ['count' => $waitingOnInvitation]) }}</p>
            <p class="mt-2 opacity-70">{{ __('A new invitation invalidates the link they were sent. The others in the selection are invited either way.') }}</p>
        </x-confirm-modal>
    @endcan

    {{-- ── Modal add to team ────────────────────────────────────────── --}}
    @can('users.update')
    <x-app-modal wire:model="addToTeamModal" :title="__('Add to a team')">
        <div class="space-y-4">
            <p class="text-sm text-base-content/60">
                {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
                {{ __('will be added to the selected team.') }}
            </p>
            <x-choices :options="$teams" class="w-full" clearable :placeholder="__('Select a team...')"
                single wire:model.live="team_id" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('addToTeamModal', false)" />
            <x-button class="btn-primary" :disabled="$team_id === null" :label="__('Add')"
                wire:click="bulkAddToTeam" spinner="bulkAddToTeam" />
        </x-slot:actions>
    </x-app-modal>
    @endcan

    {{-- ── Modal subscribe ──────────────────────────────────────────── --}}
    @can('subscriptions.manage')
    <x-app-modal wire:model="subscribeModal" :title="__('Subscribe to an event')">
        <div class="space-y-4">
            <p class="text-sm text-base-content/60">
                {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
                {{ __('will be subscribed to the selected event.') }}
            </p>
            <x-choices :options="$subscriptions" class="w-full" clearable :placeholder="__('Subscribe to...')"
                single wire:model.live="subscription_id" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('subscribeModal', false)" />
            <x-button class="btn-primary" :disabled="$subscription_id === null" :label="__('Subscribe')"
                wire:click="bulkSubscribe" spinner="bulkSubscribe" />
        </x-slot:actions>
    </x-app-modal>
    @endcan

    {{-- ── Modal anonymisation RGPD ─────────────────────────────────── --}}
    @can('users.anonymize')
    <x-app-modal wire:model="anonymizeModal" :title="__('GDPR Anonymization — Irreversible')">
        <div class="space-y-4">
            <x-alert icon="o-exclamation-triangle" class="alert-error">
                <p class="text-sm font-semibold">{{ __('This action permanently erases all personal data and cannot be undone.') }}</p>
                <p class="mt-1 text-sm">{{ __('Name, email, phone, address, IBAN and documents will be replaced with anonymous placeholders. Tournament and payment history is preserved.') }}</p>
            </x-alert>
            <x-input
                :label="__('Type ANONYMIZE to confirm')"
                wire:model.live="anonymizeConfirmText"
                :placeholder="__('ANONYMIZE')"
                class="font-mono" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('anonymizeModal', false)" />
            <x-button
                class="btn-error"
                icon="o-no-symbol"
                :label="__('Anonymize')"
                wire:click="confirmAnonymize"
                spinner="confirmAnonymize"
                :disabled="strtoupper($anonymizeConfirmText) !== 'ANONYMIZE'" />
        </x-slot:actions>
    </x-app-modal>
    @endcan

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        @can('create', \App\Domains\ClubAdmin\Users\Models\User::class)
            <x-admin.shared.mobile-action-item
                icon="o-plus" color="primary"
                :label="__('Create a member')"
                :description="__('Add a new member to the club')"
                @click="mobileActionsOpen = false; window.location.href = '{{ route('admin.users.create') }}'" />
            <x-admin.shared.mobile-action-item
                icon="o-envelope" color="info"
                :label="__('Quick invite')"
                :description="__('Send an invitation by email')"
                @click="mobileActionsOpen = false; $wire.set('quickInviteDrawer', true)" />
        @endcan
        @can('users.import')
            <x-admin.shared.mobile-action-item
                icon="o-arrow-up-tray" color="base"
                :label="__('Import the federation listing')"
                :description="__('Seed the roster from the federation affiliate listing')"
                @click="mobileActionsOpen = false; window.location.href = '{{ route('admin.users.import') }}'" />
        @endcan
        @can('setOrUpdateForceList', \App\Domains\ClubAdmin\Users\Models\User::class)
            <x-admin.shared.mobile-action-item
                icon="o-arrow-path" color="base"
                :label="__('Recalculate force list')"
                :description="__('Update player rankings used for team assignment')"
                @click="mobileActionsOpen = false; $wire.call('recalculateForceList')" />
        @endcan
        <div class="my-1 h-px bg-base-200"></div>
        <x-admin.shared.mobile-action-item
            icon="o-check-circle" color="base"
            :label="__('Select')"
            :description="__('Bulk actions on multiple members')"
            @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
    </x-admin.shared.mobile-actions>

    {{-- ── Quick invite ──────────────────────────────────────────────── --}}
    @can('create', \App\Domains\ClubAdmin\Users\Models\User::class)
    <x-drawer wire:model="quickInviteDrawer" :title="__('Quick invite')" right class="w-full max-w-sm">
        <div class="space-y-4 p-1">
            <p class="text-sm text-base-content/60">
                {{ __('Enter the minimum information to send an invitation. The member can complete their profile once logged in.') }}
            </p>
            <x-input :label="__('First name')" wire:model="inviteFirstName" :placeholder="__('Marie')" required />
            <x-input :label="__('Last name')" wire:model="inviteLastName" :placeholder="__('Dupont')" required />
            <x-input :label="__('Email')" wire:model="inviteEmail" type="email"
                :placeholder="__('marie.dupont@example.com')" required />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('quickInviteDrawer', false)" />
            <x-button class="btn-primary" icon="o-paper-airplane" :label="__('Send invitation')"
                wire:click="quickInvite" spinner="quickInvite" />
        </x-slot:actions>
    </x-drawer>
    @endcan
</div>
