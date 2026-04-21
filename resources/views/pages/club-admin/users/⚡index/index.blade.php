<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>
    <x-header progress-indicator separator title="{{ __('Users') }}">
        <x-slot:actions>
            {{-- Recherche pleine largeur --}}
            <div class="mb-6 flex items-center justify-end gap-2">
                <x-input class="w-full lg:w-72" clearable icon="o-magnifying-glass" placeholder="{{ __('Search...') }}"
                    wire:model.live.debounce="search" />

                {{-- Boutons sur la 2e ligne --}}
                {{-- Trigger à placer dans la toolbar parente --}}
                <x-button class="btn-ghost {{ $activeFiltersCount > 0 ? 'btn-active' : '' }}"
                    wire:click="$toggle('showFilters')">
                    <x-icon class="h-5 w-5" name="o-funnel" />

                    <span>{{ __('Filters') }}</span>

                    @if ($activeFiltersCount > 0)
                        <x-badge class="badge-sm badge-primary" value="{{ $activeFiltersCount }}" />
                    @endif
                </x-button>

                <x-button class="btn-primary" icon="o-plus" label="{{ __('Create') }}"
                    link="{{ route('admin.users.create') }}" responsive />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- PANNEAU FILTRES --}}
    <x-admin.shared.filter-bar :active-filters-count="$activeFiltersCount" :show="$showFilters">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Licence type') }}
                </p>
                <div class="space-y-1">
                    <x-checkbox label="{{ __('Competitive') }}" value="competitive" wire:model.live="licenceTypes" />
                    <x-checkbox label="{{ __('Recreational') }}" value="recreational" wire:model.live="licenceTypes" />
                </div>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Category') }}
                </p>
                <div class="space-y-1">
                    <x-checkbox label="{{ __('Men') }}" value="men" wire:model.live="categories" />
                    <x-checkbox label="{{ __('Women') }}" value="women" wire:model.live="categories" />
                    <x-checkbox label="{{ __('Youth') }}" value="youth" wire:model.live="categories" />
                </div>
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Status') }}
                </p>
                <x-toggle label="{{ __('Active members only') }}" wire:model.live="onlyActive" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Teams') }}
                </p>
                <x-choices :options="$teams" class="w-full" clearable placeholder="{{ __('Select a team...') }}"
                    wire:model.live="team_ids" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-bar>

    {{-- BARRE BULK (contextuelle) --}}
    @if (count($selected) > 0)
        <x-admin.shared.bulk-bar :selected="$selected">
            <x-slot:actions>
                <div class="border-base-200 flex items-center gap-2 border-r pr-3">
                    <div class="min-w-44 shrink-0">
                        <x-choices :options="$teams" class="w-full" clearable
                            placeholder="{{ __('Add to a team...') }}" single wire:model.live="team_id" />
                    </div>
                    <x-button :disabled="$team_id === null" class="btn-ghost btn-sm" label="{{ __('Add') }}"
                        wire:click="bulkAddToTeam" />
                </div>

                <div class="border-base-200 flex items-center gap-2 border-r pr-3">
                    <div class="min-w-44 shrink-0">
                        <x-choices :options="$subscriptions" class="w-full" clearable placeholder="{{ __('Subscribe to...') }}"
                            single wire:model.live="subscription_id" />
                    </div>
                    <x-button :disabled="$subscription_id === null" class="btn-ghost btn-sm" label="{{ __('Subscribe') }}"
                        wire:click="bulkSubscribe" />
                </div>

                <div class="flex items-center gap-1">
                    <x-button class="btn-ghost btn-sm" label="{{ __('Activate') }}" wire:click="bulkActivate" />
                    <span class="text-base-content/20 text-sm">/</span>
                    <x-button class="btn-ghost btn-sm" label="{{ __('Deactivate') }}" wire:click="bulkDeactivate" />
                </div>

                <x-button class="btn-ghost btn-sm text-error" icon="o-trash" label="{{ __('Delete') }}"
                    wire:click="confirmBulkDelete" />
            </x-slot:actions>
        </x-admin.shared.bulk-bar>
    @endif

    {{-- VUE MOBILE (cards)    --}}
    <div class="grid grid-cols-1 gap-4 lg:hidden">
        @foreach ($users as $user)
            <x-list-item :item="$user" class="bg-base-100 rounded-lg border">
                <x-slot:avatar>
                    <x-avatar :image="$user->photo ?? '/images/empty-user.jpg'" class="!w-10" />
                </x-slot:avatar>

                <x-slot:value>
                    {{ $user->first_name }} {{ $user->last_name }}
                </x-slot:value>

                <x-slot:sub-value>
                    <div class="flex flex-col">
                        <span class="text-accent font-medium">
                            {{ $user->licence_type === 'competitive' ? $user->ranking . ' — Ottignies A' : __('Recreational') }}
                        </span>
                        <span class="text-xs opacity-50">{{ $user->email }}</span>
                        <span class="text-xs opacity-50">{{ $user->phone_number }}</span>
                    </div>
                </x-slot:sub-value>

                <x-slot:actions>
                    <x-dropdown>
                        <x-slot:trigger>
                            <x-button class="btn-ghost btn-sm" icon="o-ellipsis-vertical" />
                        </x-slot:trigger>
                        <x-menu-item class="text-xs" icon="o-eye" link="{{ route('admin.users.edit', $user) }}"
                            title="{{ __('View details') }}" />
                        <x-menu-item class="text-xs" icon="o-pencil" link="{{ route('admin.users.edit', $user) }}"
                            title="{{ __('Edit') }}" />
                        <x-menu-item class="text-xs" icon="o-envelope" link="#"
                            title="{{ __('Send message') }}" />
                        <x-menu-separator />
                        <x-menu-item class="text-error text-xs" icon="o-trash" title="{{ __('Delete') }}"
                            wire:click="confirmDelete({{ $user->id }})" />
                    </x-dropdown>
                </x-slot:actions>
            </x-list-item>
        @endforeach
    </div>

    {{-- VUE TABLE (desktop)   --}}
    <div class="hidden lg:block">
        <x-card>
            <x-table :headers="$headers" :rows="$users" :sort-by="$sortBy" selectable wire:model.live="selected">
                @scope('cell_photo', $user)
                    <x-avatar class="h-10 w-10" image="{{ $user->photo ?? '/images/empty-user.jpg' }}" />
                @endscope
                @scope('cell_name', $user)
                    {{ $user->first_name }} {{ $user->last_name }}
                @endscope
                @scope('cell_is_competitive', $user)
                    @if ($user->is_competitor)
                        <span class="font-medium text-accent"><x-icon name="o-trophy" class="h-5 w-5" /> {{ __('Competitive') }}</span>
                    @else
                        <span class="font-medium text-accent">{{ __('Recreational') }}</span>
                    @endif
                @endscope
                @scope('actions', $user)
                    <x-admin.shared.row-actions>
                        <x-menu-item icon="o-eye" link="{{ route('admin.users.edit', $user->id) }}"
                            title="{{ __('View details') }}" />
                        <x-menu-item icon="o-pencil" link="{{ route('admin.users.edit', $user->id) }}"
                            title="{{ __('Edit') }}" />
                        <x-menu-item icon="o-envelope" link="#" title="{{ __('Send message') }}" />
                        <x-menu-separator />
                        <x-menu-item class="text-error" icon="o-trash" title="{{ __('Delete') }}"
                            wire:click="confirmDelete({{ $user->id }})" />
                    </x-admin.shared.row-actions>
                @endscope
            </x-table>
            <div class="mt-4">
                {{ $users->links() }}
            </div>
        </x-card>
    </div>

    {{-- MODALES               --}}
    <x-modal subtitle="{{ __('Warning!') }}" title="{{ __('Confirm deletion') }}" wire:model="deleteModal">
        <p>{{ __('Are you sure you want to delete this user? This action is irreversible.') }}</p>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="{{ __('Delete') }}" spinner wire:click="delete" />
        </x-slot:actions>
    </x-modal>

    <x-modal subtitle="{{ __('Warning!') }}" title="{{ __('Confirm bulk deletion') }}"
        wire:model="deleteSelectedModal">
        <p>{{ __('Are you sure you want to delete the selected users? This action is irreversible.') }}</p>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('deleteSelectedModal', false)" />
            <x-button class="btn-error" label="{{ __('Delete') }}" spinner wire:click="deleteSelected" />
        </x-slot:actions>
    </x-modal>
</div>