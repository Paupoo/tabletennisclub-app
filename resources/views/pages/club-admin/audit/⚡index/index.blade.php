<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div x-data="{ mobileSearchOpen: false }">
    <x-header :title="__('Audit')" :subtitle="__('Everything that happens in the admin')" separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search by member or item type...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-more="false" />
            {{-- Desktop --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-200 lg:hidden" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search by member or item type...') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- Active filter chips --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Vue mobile ───────────────────────────────────────────────── --}}
    <div class="mt-6 grid grid-cols-1 gap-3 lg:hidden">
        @forelse ($activities as $activity)
            @php
                $event = $activity->event ?? $activity->description;
                $changes = $activity->attribute_changes;
                $subjectName = $subjectLabels[$activity->subject_type] ?? \Illuminate\Support\Str::afterLast($activity->subject_type, '\\');
                $formatValue = fn ($value) => \Illuminate\Support\Str::limit(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value, 60);
            @endphp
            <x-card class="border border-base-200 bg-base-100 shadow-sm" wire:key="mobile-activity-{{ $activity->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        @if ($event === 'created')
                            <x-badge :value="__('Created')" class="badge-success badge-sm badge-soft" />
                        @elseif ($event === 'updated')
                            <x-badge :value="__('Modified')" class="badge-info badge-sm badge-soft" />
                        @elseif ($event === 'deleted')
                            <x-badge :value="__('Deleted')" class="badge-error badge-sm badge-soft" />
                        @else
                            <x-badge :value="$activity->description" class="badge-ghost badge-sm" />
                        @endif
                        <span class="text-sm font-semibold">{{ $subjectName }}</span>
                        <span class="font-mono text-xs text-muted">#{{ $activity->subject_id }}</span>
                    </div>
                    <span class="shrink-0 text-xs tabular-nums text-muted">{{ $activity->created_at->format('d/m/Y H:i') }}</span>
                </div>

                <div class="mt-2 flex items-center gap-1.5 text-xs text-base-content/60">
                    <x-icon name="o-user" class="h-3.5 w-3.5 shrink-0 opacity-50" />
                    @if ($activity->causer)
                        <span class="font-medium">{{ $activity->causer->first_name }} {{ $activity->causer->last_name }}</span>
                    @else
                        <span class="italic opacity-70">{{ __('System') }}</span>
                    @endif
                </div>

                @if ($changes && isset($changes['attributes']))
                    <div x-data="{ open: {{ $event === 'created' ? 'false' : 'true' }} }" class="mt-3 border-t border-base-200 pt-2">
                        {{-- py-1.5 : le bouton ne faisait que la hauteur de son texte,
                        sous le plancher de 24px du WCAG 2.2. --}}
                        <button type="button" @click="open = !open"
                            class="flex w-full items-center justify-between py-1.5 text-xs font-semibold text-base-content/60">
                            <span class="flex items-center gap-1.5">
                                <x-icon name="o-pencil-square" class="h-3.5 w-3.5 opacity-60" />
                                {{ __('Details') }}
                                <x-badge value="{{ count($changes['attributes']) }}" class="badge-ghost badge-xs" />
                            </span>
                            <x-icon name="o-chevron-down" class="h-4 w-4 transition-transform" x-bind:class="open && 'rotate-180'" />
                        </button>
                        <div x-show="open" x-transition style="{{ $event === 'created' ? 'display:none' : '' }}" class="mt-2 space-y-1">
                            @foreach ($changes['attributes'] as $field => $newValue)
                                <div class="text-xs">
                                    <span class="font-semibold opacity-70">{{ $field }}:</span>
                                    @if ($event !== 'created' && isset($changes['old'][$field]) && $changes['old'][$field] !== null && $changes['old'][$field] !== '')
                                        <span class="text-error/70 line-through">{{ $formatValue($changes['old'][$field]) }}</span>
                                        <span class="text-muted">→</span>
                                    @endif
                                    <span class="break-all text-success/80">{{ $formatValue($newValue) ?: '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-card>
        @empty
            <x-admin.shared.list-empty-state
                icon="o-document-magnifying-glass"
                :heading="__('No activity recorded yet.')"
                :filtered="count($filterChips) > 0 || filled($search)" />
        @endforelse

        @if ($activities->hasPages())
            <div class="mt-2">
                {{ $activities->links() }}
            </div>
        @endif
    </div>

    {{-- ── Vue desktop ────────────────────────────────────────────────── --}}
    <x-card class="bg-base-100 shadow-sm mt-6 hidden lg:block">
        <x-table :headers="$headers" :rows="$activities" :sort-by="$sortBy" hover>

            @scope('cell_created_at', $activity)
            <span class="text-sm tabular-nums whitespace-nowrap">{{ $activity->created_at->format('d/m/Y H:i') }}</span>
            @endscope

            @scope('cell_causer', $activity)
            @if ($activity->causer)
            <span class="text-sm font-medium">{{ $activity->causer->first_name }} {{ $activity->causer->last_name }}</span>
            @else
            <span class="text-sm italic text-muted">{{ __('System') }}</span>
            @endif
            @endscope

            @scope('cell_event', $activity)
            @php $event = $activity->event ?? $activity->description; @endphp
            @if ($event === 'created')
            <x-badge :value="__('Created')" class="badge-success badge-sm badge-soft" />
            @elseif ($event === 'updated')
            <x-badge :value="__('Modified')" class="badge-info badge-sm badge-soft" />
            @elseif ($event === 'deleted')
            <x-badge :value="__('Deleted')" class="badge-error badge-sm badge-soft" />
            @else
            <x-badge :value="$activity->description" class="badge-ghost badge-sm" />
            @endif
            @endscope

            @scope('cell_subject', $activity, $subjectLabels)
            <div>
                <div class="text-sm font-medium">{{ $subjectLabels[$activity->subject_type] ?? \Illuminate\Support\Str::afterLast($activity->subject_type, '\\') }}</div>
                <div class="font-mono text-xs text-muted">#{{ $activity->subject_id }}</div>
            </div>
            @endscope

            @scope('cell_changes', $activity)
            @php $changes = $activity->attribute_changes; @endphp
            @php $event = $activity->event ?? $activity->description; @endphp
            @php $formatValue = fn ($value) => \Illuminate\Support\Str::limit(is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value, 40); @endphp
            @if ($changes && isset($changes['attributes']))
                @if ($event === 'created')
                {{-- Création : repliée par défaut (sinon mur de champs) --}}
                <div x-data="{ open: false }" class="text-xs">
                    <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1 text-primary hover:underline">
                        <x-icon name="o-eye" class="h-3.5 w-3.5" />
                        <span x-show="!open">{{ trans_choice('{1} :count field set|[2,*] :count fields set', count($changes['attributes']), ['count' => count($changes['attributes'])]) }}</span>
                        <span x-show="open" style="display:none">{{ __('Hide details') }}</span>
                    </button>
                    <div x-show="open" x-transition style="display:none" class="mt-1 space-y-0.5">
                        @foreach ($changes['attributes'] as $field => $newValue)
                        <div>
                            <span class="font-semibold opacity-70">{{ $field }}:</span>
                            <span class="text-success/80">{{ $formatValue($newValue) ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                {{-- Modification / suppression : le diff est inline tant qu'il se lit
                d'un coup d'œil. Au-delà de trois champs il se replie, comme la
                création juste au-dessus : une modification de masse déversait
                jusqu'à 649 caractères dans une cellule, qui étirait la ligne et
                repoussait le reste du tableau hors de l'écran. --}}
                @php $isLongDiff = count($changes['attributes']) > 3; @endphp
                <div @if ($isLongDiff) x-data="{ open: false }" @endif class="space-y-0.5">
                    @if ($isLongDiff)
                    <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1 py-1 text-xs text-primary hover:underline">
                        <x-icon name="o-eye" class="h-3.5 w-3.5" />
                        <span x-show="!open">{{ trans_choice('{1} :count field changed|[2,*] :count fields changed', count($changes['attributes']), ['count' => count($changes['attributes'])]) }}</span>
                        <span x-show="open" style="display:none">{{ __('Hide details') }}</span>
                    </button>
                    @endif
                    <div @if ($isLongDiff) x-show="open" x-transition style="display:none" @endif class="space-y-0.5">
                        @foreach ($changes['attributes'] as $field => $newValue)
                        <div class="text-xs">
                            <span class="font-semibold opacity-70">{{ $field }}:</span>
                            @if (isset($changes['old'][$field]) && $changes['old'][$field] !== null && $changes['old'][$field] !== '')
                            <span class="text-error/70 line-through">{{ $formatValue($changes['old'][$field]) }}</span>
                            <span class="text-muted">→</span>
                            @endif
                            <span class="text-success/80">{{ $formatValue($newValue) ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            @else
            <span class="opacity-30">—</span>
            @endif
            @endscope

        </x-table>

        @if ($activities->total() === 0)
        <div class="flex flex-col items-center justify-center py-12 text-muted">
            <x-icon name="o-document-magnifying-glass" class="w-12 h-12 mb-4" />
            <p class="text-sm italic">{{ __('No activity recorded yet.') }}</p>
        </div>
        @endif

        <div class="mt-4">
            {{ $activities->links() }}
        </div>
    </x-card>

    {{-- ========================================== --}}
    {{-- Filter drawer                              --}}
    {{-- ========================================== --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <x-select
                :label="__('Item type')"
                wire:model.live="modelFilter"
                :options="$modelOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All')"
                clearable />

            <x-select
                :label="__('Author')"
                wire:model.live="causerFilter"
                :options="$causerOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All')"
                clearable />

            <x-select
                :label="__('Action')"
                wire:model.live="eventFilter"
                :options="$eventOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All')"
                clearable />

            <x-input
                :label="__('From')"
                wire:model.live="dateFrom"
                type="date" />

            <x-input
                :label="__('To')"
                wire:model.live="dateTo"
                type="date" />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
