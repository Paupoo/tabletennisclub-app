<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :subtitle="__('Find and contact other club members')" :title="__('Member directory')">
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
                            {{-- Surname first, so the alphabetical order of the grid reads at a glance. --}}
                            <p class="truncate">
                                <span class="font-semibold">{{ $member->last_name }}</span>
                                <span class="text-base-content/70">{{ $member->first_name }}</span>
                            </p>
                            <div class="mt-0.5 flex items-center gap-2 text-xs text-base-content/60">
                                <span class="font-mono">{{ $member->ranking->getLabel() }}</span>
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

                    @php
                        // Un mineur se joint par son tuteur : c'est souvent la seule
                        // coordonnée qui existe, l'enfant n'ayant ni ligne ni boîte mail.
                        $guardian = $member->isMinor() ? $member->guardians->first() : null;
                        $guardianName = $guardian ? trim($guardian->first_name . ' ' . $guardian->last_name) : null;
                        // Le numéro vit à deux endroits et la plupart des mineurs n'ont
                        // aucun Guardian lié : la colonne portée par la fiche du membre
                        // fait le reste (même lecture que l'écran coach).
                        $guardianPhone = $member->isMinor() ? ($guardian?->phone ?: $member->guardian_phone_number) : null;
                        $guardianEmail = $guardian?->email;
                        // C'est le consentement du pupille qui commande : les coordonnées
                        // du tuteur tiennent lieu des siennes, et les publier plus
                        // largement que les siennes exposerait un tiers qui n'a rien coché.
                        $showGuardianPhone = $guardianPhone && $member->contactVisibleTo($viewer, 'phone');
                        $showGuardianEmail = $guardianEmail && $member->contactVisibleTo($viewer, 'email');
                        $showGuardian = $showGuardianPhone || $showGuardianEmail;
                    @endphp

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
                    @elseif (! $showGuardian)
                        <p class="mt-1 border-t border-base-300 pt-3 text-xs text-base-content/40">
                            {{ __('No shared contact details') }}
                        </p>
                    @endif

                    {{-- Responsible adult — for a minor this is who the club actually calls. --}}
                    @if ($showGuardian)
                        <div class="mt-1 rounded-lg bg-base-200/60 p-3 text-sm">
                            <p class="text-xs font-bold uppercase tracking-widest text-muted">
                                {{ __('Responsible adult') }}
                            </p>
                            @if ($guardianName)
                                <p class="mt-1 truncate font-medium">{{ $guardianName }}</p>
                            @endif
                            <div class="mt-1 space-y-1.5">
                                @if ($showGuardianPhone)
                                    <a href="tel:{{ $guardianPhone }}"
                                        class="flex items-center gap-2 text-base-content/80 hover:text-primary">
                                        <x-icon name="o-phone" class="size-4 shrink-0 text-base-content/40" />
                                        <span class="truncate">{{ $guardianPhone }}</span>
                                    </a>
                                @endif
                                @if ($showGuardianEmail)
                                    <a href="mailto:{{ $guardianEmail }}"
                                        class="flex items-center gap-2 text-base-content/80 hover:text-primary">
                                        <x-icon name="o-envelope" class="size-4 shrink-0 text-base-content/40" />
                                        <span class="truncate">{{ $guardianEmail }}</span>
                                    </a>
                                @endif
                            </div>
                        </div>
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
