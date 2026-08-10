<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header separator :subtitle="__('Find and contact other club members')" :title="__('Member directory')">
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass" :placeholder="__('Search a member...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            <x-admin.shared.filters-button :count="count($filterChips)" class="btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Mobile search --}}
    <div class="mb-4 lg:hidden">
        <x-input class="w-full" clearable icon="o-magnifying-glass" :placeholder="__('Search a member...')"
            wire:model.live.debounce.300ms="search" />
    </div>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    @php $viewer = auth()->user(); @endphp

    @if ($this->members->isEmpty())
        <x-admin.shared.list-empty-state
            icon="o-users"
            :heading="__('No members in the directory')"
            :filtered="count($filterChips) > 0 || filled($search)" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->members as $member)
                <div class="flex flex-col gap-3 rounded-xl border border-base-300 bg-base-100 p-4 transition-colors hover:border-primary">
                    {{-- Identity --}}
                    <div class="flex items-center gap-3">
                        <x-avatar :image="$member->photo ?? '/images/empty-user.jpg'" class="!w-11 !rounded-full" />
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $member->first_name }} {{ $member->last_name }}</p>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-base-content/60">
                                <span class="font-mono">{{ $member->ranking ?: '—' }}</span>
                                @if ($member->force_list)
                                    <span class="text-base-content/30">·</span>
                                    <span>{{ __('Force') }} {{ $member->force_list }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Teams (name + category so "A" reads as e.g. "A · Veterans") --}}
                    @if ($member->teams->isNotEmpty())
                        <div class="flex flex-wrap gap-1">
                            @foreach ($member->teams as $team)
                                @php $category = \App\Domains\Shared\Enums\LeagueCategory::fromName($team->league?->category); @endphp
                                <x-badge
                                    :value="$category ? $team->name . ' · ' . $category->label() : $team->name"
                                    class="badge-sm {{ $category?->badgeClasses() ?? 'badge-ghost' }}" />
                            @endforeach
                        </div>
                    @endif

                    {{-- Contact (only what the member shares; committee/self always see) --}}
                    @php $showPhone = $member->phone_number && $member->contactVisibleTo($viewer, 'phone'); @endphp
                    @php $showEmail = $member->email && $member->contactVisibleTo($viewer, 'email'); @endphp
                    @php $showAddress = filled($member->street) && $member->contactVisibleTo($viewer, 'address'); @endphp

                    @if ($showPhone || $showEmail || $showAddress)
                        <div class="mt-1 space-y-1.5 border-t border-base-300 pt-3 text-sm">
                            @if ($showPhone)
                                <a href="tel:{{ $member->phone_number }}"
                                    class="flex items-center gap-2 text-base-content/80 hover:text-primary">
                                    <x-icon name="o-phone" class="size-4 shrink-0 text-base-content/40" />
                                    <span class="truncate">{{ $member->phone_number }}</span>
                                </a>
                            @endif
                            @if ($showEmail)
                                <a href="mailto:{{ $member->email }}"
                                    class="flex items-center gap-2 text-base-content/80 hover:text-primary">
                                    <x-icon name="o-envelope" class="size-4 shrink-0 text-base-content/40" />
                                    <span class="truncate">{{ $member->email }}</span>
                                </a>
                            @endif
                            @if ($showAddress)
                                <p class="flex items-start gap-2 text-base-content/80">
                                    <x-icon name="o-map-pin" class="mt-0.5 size-4 shrink-0 text-base-content/40" />
                                    <span>{{ $member->street }}, {{ $member->city_code }} {{ $member->city_name }}</span>
                                </p>
                            @endif
                        </div>
                    @else
                        <p class="mt-1 border-t border-base-300 pt-3 text-xs text-base-content/40">
                            {{ __('No shared contact details') }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $this->members->links() }}
        </div>
    @endif

    {{-- Filter drawer --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Ranking') }}
                </p>
                <x-select wire:model.live="rankingFilter" :placeholder="__('All rankings')"
                    :options="collect($this->rankingsForFilter)->map(fn ($r) => ['id' => $r, 'name' => $r])->all()" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Season') }}
                </p>
                <x-select wire:model.live="seasonFilter" :options="$this->seasonsForFilter" />
            </div>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Team') }}
                </p>
                <x-select wire:model.live="teamFilter" :placeholder="__('All teams')"
                    :options="$this->teamsForFilter->all()" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
