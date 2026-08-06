<div x-data="{ mobileSearchOpen: false }">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Season roster')"
        :subtitle="$season?->name">
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search by name…')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-200 lg:hidden" x-show="mobileSearchOpen"
        x-transition style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search by name…') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    <x-card>
        @if ($rows->isEmpty())
            <x-empty-state
                icon="o-users"
                :heading="__('No members found')"
                :message="__('Try adjusting your search or filters.')" />
        @else
            <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy">
                @scope('cell_name', $row)
                    <span class="font-medium">{{ $row->name }}</span>
                @endscope

                @scope('cell_ranking', $row)
                    <span class="text-sm text-base-content/70">{{ $row->ranking ?? '—' }}</span>
                @endscope

                @scope('cell_age_category', $row)
                    @if ($row->age_label)
                        <x-badge :value="$row->age_label" class="badge-ghost badge-sm" />
                    @else
                        <span class="text-base-content/30">—</span>
                    @endif
                @endscope

                @scope('cell_is_competitive', $row)
                    <div class="flex items-center gap-1">
                        @if ($row->is_competitive)
                            <x-badge :value="__('Yes')" class="badge-success badge-soft badge-sm" />
                        @else
                            <span class="text-base-content/30">—</span>
                        @endif
                        {{--
                            La fédération dit autre chose. Ni bloquant ni corrigé
                            automatiquement : le club décide, mais il doit le voir.
                        --}}
                        @if ($row->federation_gap)
                            <x-icon name="o-exclamation-triangle" class="h-4 w-4 text-warning"
                                :title="$row->federation_gap" />
                        @endif
                    </div>
                @endscope

                @scope('cell_can_drive', $row, $canManage)
                    <div class="flex items-center gap-2">
                        <x-toggle wire:key="drive-{{ $row->id }}"
                            :checked="$row->can_drive" :disabled="! $canManage"
                            wire:change="updateAttribute({{ $row->id }}, 'can_drive', $event.target.checked)" />
                        @if ($row->can_drive)
                            @if ($canManage)
                                <x-input type="number" min="0" max="8"
                                    class="input-xs w-16"
                                    wire:key="seats-{{ $row->id }}"
                                    :value="$row->seats_available"
                                    wire:change="updateSeats({{ $row->id }}, $event.target.value === '' ? null : parseInt($event.target.value))" />
                            @else
                                <span class="text-xs text-base-content/60">{{ $row->seats_available ?? '—' }}</span>
                            @endif
                        @endif
                    </div>
                @endscope

                @scope('cell_wants_to_be_captain', $row, $canManage)
                    <x-toggle wire:key="captain-{{ $row->id }}"
                        :checked="$row->wants_to_be_captain" :disabled="! $canManage"
                        wire:change="updateAttribute({{ $row->id }}, 'wants_to_be_captain', $event.target.checked)" />
                @endscope

                @scope('cell_volunteer_help', $row, $canManage)
                    <x-toggle wire:key="volunteer-{{ $row->id }}"
                        :checked="$row->volunteer_help" :disabled="! $canManage"
                        wire:change="updateAttribute({{ $row->id }}, 'volunteer_help', $event.target.checked)" />
                @endscope

                @scope('cell_wants_directed_training', $row, $canManage)
                    <x-toggle wire:key="directed-{{ $row->id }}"
                        :checked="$row->wants_directed_training" :disabled="! $canManage"
                        wire:change="updateAttribute({{ $row->id }}, 'wants_directed_training', $event.target.checked)" />
                @endscope
            </x-table>

            <div class="mt-4">
                {{ $rows->links() }}
            </div>
        @endif
    </x-card>

    {{-- ── Filter drawer ─────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Competitive') }}
                </p>
                <x-select :options="$triStateOptions" :placeholder="__('Any')"
                    wire:model.live="competitive" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Can drive') }}
                </p>
                <x-select :options="$triStateOptions" :placeholder="__('Any')"
                    wire:model.live="canDrive" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Captain') }}
                </p>
                <x-select :options="$triStateOptions" :placeholder="__('Any')"
                    wire:model.live="wantsToBeCaptain" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Volunteer') }}
                </p>
                <x-select :options="$triStateOptions" :placeholder="__('Any')"
                    wire:model.live="volunteerHelp" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Directed') }}
                </p>
                <x-select :options="$triStateOptions" :placeholder="__('Any')"
                    wire:model.live="wantsDirectedTraining" class="w-full" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">
                    {{ __('Age category') }}
                </p>
                <x-select :options="$ageCategoryOptions" :placeholder="__('All age categories')"
                    wire:model.live="ageCategory" class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
