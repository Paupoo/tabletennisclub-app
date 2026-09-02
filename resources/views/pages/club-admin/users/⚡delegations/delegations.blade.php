<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div x-data="{ mobileSearchOpen: false }">
    <x-header :title="__('Delegations')"
        :subtitle="__('Who holds which duty — and which duties nobody covers')"
        separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search a member...')"
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
    <div class="border-b border-base-300 lg:hidden" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search a member...') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    @if (count($filterChips) > 0)
        <div class="flex flex-wrap gap-2 py-3">
            @foreach ($filterChips as $chip)
                <button wire:click="removeFilter('{{ $chip['key'] }}')"
                    class="badge badge-primary badge-soft gap-1 py-3">
                    {{ $chip['label'] }}
                    <x-icon name="o-x-mark" class="h-3 w-3" />
                </button>
            @endforeach
        </div>
    @endif

    {{--
        Uncovered duties first. A committee opens this screen to find the holes,
        not to admire the coverage.
    --}}
    @if ($this->uncoveredDelegations->isNotEmpty())
        <div class="mb-6 rounded-xl border border-warning/40 bg-warning/5 p-4">
            <div class="flex items-start gap-3">
                <x-icon name="o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-warning" />
                <div class="min-w-0">
                    {{-- Two plain strings rather than trans_choice: inline pluralisation
                         is not looked up in the JSON translation files, so it would
                         have shipped this line in English. --}}
                    <p class="text-sm font-semibold text-base-content">
                        @if ($this->uncoveredDelegations->count() === 1)
                            {{ __('1 delegation has no holder') }}
                        @else
                            {{ __(':count delegations have no holder', ['count' => $this->uncoveredDelegations->count()]) }}
                        @endif
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($this->uncoveredDelegations as $role)
                            <span class="badge badge-sm badge-warning badge-soft">{{ $role->label() }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- View switch --}}
    <div role="tablist" class="tabs tabs-box mb-4 w-full sm:w-auto sm:inline-flex">
        <button role="tab" wire:click="$set('view', 'delegations')"
            @class(['tab flex-1 sm:flex-none', 'tab-active' => $view === 'delegations'])>
            <x-icon name="o-key" class="mr-1.5 h-4 w-4" />
            {{ __('By delegation') }}
        </button>
        <button role="tab" wire:click="$set('view', 'members')"
            @class(['tab flex-1 sm:flex-none', 'tab-active' => $view === 'members'])>
            <x-icon name="o-users" class="mr-1.5 h-4 w-4" />
            {{ __('By member') }}
        </button>
    </div>

    @if ($view === 'delegations')
        {{-- One card per duty. Single column on a phone, paired from lg. --}}
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            @foreach ($this->delegationRows as $row)
                <div class="rounded-xl border border-base-300 bg-base-100 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-base-content">{{ $row['role']->label() }}</p>
                            <p class="mt-0.5 text-xs leading-snug text-base-content/60">
                                {{ $row['role']->description() }}
                            </p>
                        </div>
                        <span @class([
                            'badge badge-sm shrink-0',
                            'badge-primary badge-soft' => $row['holders']->isNotEmpty(),
                            'badge-warning badge-soft' => $row['holders']->isEmpty(),
                        ])>
                            {{ $row['holders']->count() }}
                        </span>
                    </div>

                    @if ($row['holders']->isEmpty())
                        <p class="mt-3 text-xs italic text-base-content/50">
                            {{ __('Nobody holds this delegation.') }}
                        </p>
                    @else
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($row['holders'] as $holder)
                                <a href="{{ route('admin.users.edit', $holder) }}"
                                    class="inline-flex items-center gap-1 rounded-full border border-base-300 px-2.5 py-1 text-xs transition-colors duration-150 hover:border-primary">
                                    {{ $holder->first_name }} {{ $holder->last_name }}
                                    @if ($holder->committee_role)
                                        <span class="text-base-content/40">·</span>
                                        <span class="text-base-content/60">{{ $holder->committee_role->label() }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        @if ($this->memberRows->isEmpty())
            <div class="rounded-xl border border-base-300 bg-base-100 p-8 text-center">
                <x-icon name="o-users" class="mx-auto h-8 w-8 text-base-content/30" />
                <p class="mt-2 text-sm text-base-content/60">{{ __('No member holds a delegation yet.') }}</p>
            </div>
        @else
            {{-- Cards on a phone: 16 duties across a table would scroll sideways. --}}
            <div class="space-y-3 md:hidden">
                @foreach ($this->memberRows as $row)
                    <a href="{{ route('admin.users.edit', $row['user']) }}"
                        class="block rounded-xl border border-base-300 bg-base-100 p-4 transition-colors duration-150 hover:border-primary">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-sm font-semibold text-base-content">
                                {{ $row['user']->first_name }} {{ $row['user']->last_name }}
                            </p>
                            @if ($row['user']->committee_role)
                                <span class="badge badge-sm badge-secondary shrink-0 text-secondary-content">
                                    {{ $row['user']->committee_role->label() }}
                                </span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($row['roles'] as $role)
                                <span class="badge badge-sm badge-primary badge-soft">{{ $role->label() }}</span>
                            @endforeach
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- Table from md, scrolling inside its own box rather than the page. --}}
            <div class="hidden overflow-x-auto rounded-xl border border-base-300 md:block">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>{{ __('Member') }}</th>
                            <th>{{ __('Statutory title') }}</th>
                            <th>{{ __('Delegations') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->memberRows as $row)
                            <tr class="hover">
                                <td class="whitespace-nowrap font-medium">
                                    <a href="{{ route('admin.users.edit', $row['user']) }}"
                                        class="hover:text-primary">
                                        {{ $row['user']->first_name }} {{ $row['user']->last_name }}
                                    </a>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($row['user']->committee_role)
                                        <span class="badge badge-sm badge-secondary text-secondary-content">
                                            {{ $row['user']->committee_role->label() }}
                                        </span>
                                    @else
                                        <span class="text-base-content/40">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach ($row['roles'] as $role)
                                            <span class="badge badge-sm badge-primary badge-soft">
                                                {{ $role->label() }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- Legend: the three families must stay tellable apart at a glance. Sample
         swatches rather than empty badges, which rendered near-invisible. --}}
    <div class="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-base-content/60">
        <span class="flex items-center gap-2">
            <span class="badge badge-sm badge-primary badge-soft">{{ __('Treasury') }}</span>
            {{ __('Delegation — decides what one may do') }}
        </span>
        <span class="flex items-center gap-2">
            <span class="badge badge-sm badge-secondary text-secondary-content">{{ __('Treasurer') }}</span>
            {{ __('Statutory title — displayed, grants nothing') }}
        </span>
    </div>

    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <x-select
                :label="__('Delegation')"
                wire:model.live="delegationFilter"
                :options="$this->delegationOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All')"
                clearable />

            <x-input
                :label="__('Member')"
                wire:model.live.debounce.300ms="search"
                :placeholder="__('Name or email')" />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
