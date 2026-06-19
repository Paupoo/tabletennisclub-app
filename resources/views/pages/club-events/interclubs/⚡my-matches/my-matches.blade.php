<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('My Matches')" :subtitle="__('Manage your availability for upcoming interclubs')"
        separator />

    @if (! $grouped->isEmpty())
        <div class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border border-base-200 bg-base-200/40 px-4 py-3">
            <span class="text-xs font-semibold opacity-50">{{ __('Set for all upcoming matches:') }}</span>
            <x-button
                class="btn-success btn-xs btn-soft"
                icon="o-check-circle"
                :label="__('All available')"
                wire:click="bulkMarkAvailability('available')" />
            <x-button
                class="btn-warning btn-xs btn-soft"
                icon="o-question-mark-circle"
                :label="__('All maybe')"
                wire:click="bulkMarkAvailability('maybe')" />
            <x-button
                class="btn-error btn-xs btn-soft"
                icon="o-x-circle"
                :label="__('All unavailable')"
                wire:click="$set('bulkUnavailableModal', true)" />
        </div>
    @endif

    @if ($grouped->isEmpty())
        <x-empty-state icon="o-calendar" :title="__('No upcoming matches')"
            :description="__('You are not registered in any team for this season.')" />
    @else
        @php
            $catMeta = [
                'Hommes'   => ['bg' => 'bg-blue-50',  'border' => 'border-blue-200',  'text' => 'text-blue-700',  'dot' => 'bg-blue-500'],
                'Vétérans' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
                'Dames'    => ['bg' => 'bg-pink-50',  'border' => 'border-pink-200',  'text' => 'text-pink-700',  'dot' => 'bg-pink-500'],
            ];
        @endphp

        <div class="space-y-10">
            @foreach ($grouped as $catLabel => $teamGroups)
                @php $cat = $catMeta[$catLabel] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400']; @endphp

                <section x-data="{ open: true }">
                    {{-- Category header --}}
                    <button type="button" class="mb-6 flex w-full items-center gap-3 text-left" @click="open = !open">
                        <span class="inline-flex items-center gap-2 rounded-full {{ $cat['bg'] }} {{ $cat['border'] }} border px-4 py-1.5">
                            <span class="h-2 w-2 rounded-full {{ $cat['dot'] }}"></span>
                            <span class="text-sm font-bold {{ $cat['text'] }} uppercase tracking-wide">{{ $catLabel }}</span>
                            <span class="text-xs {{ $cat['text'] }} opacity-60">{{ $teamGroups->count() }} équipe{{ $teamGroups->count() > 1 ? 's' : '' }}</span>
                        </span>
                        <div class="flex-1 border-t {{ $cat['border'] }}"></div>
                        <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200" ::class="open ? '' : '-rotate-90'" />
                    </button>

                    <div x-show="open" x-collapse>
                        <div class="space-y-8">
                            @foreach ($teamGroups as $teamName => $matches)
                                <div x-data="{ open: true }">
                                    <button
                                        type="button"
                                        class="mb-3 flex w-full items-center gap-2 text-left"
                                        @click="open = !open"
                                    >
                                        <x-icon name="o-user-group" class="h-4 w-4 opacity-40" />
                                        <span class="flex-1 text-sm font-black uppercase tracking-widest opacity-60">{{ $teamName }}</span>
                                        <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200" ::class="open ? '' : '-rotate-90'" />
                                    </button>

                                    <div x-show="open" x-collapse class="divide-base-200 border-base-200 divide-y overflow-hidden rounded-2xl border">
                                        @foreach ($matches as $match)
                            @php
                                $avail = $match['availability'];
                                $urgency = $match['days_until'] <= 7 && $avail === null;
                            @endphp
                            <div @class([
                                'p-4 bg-base-100 transition-colors',
                                'bg-warning/5' => $urgency,
                            ])>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                                    {{-- Info match --}}
                                    <div class="flex min-w-0 flex-1 items-center gap-4">
                                        {{-- Numéro semaine --}}
                                        <div class="bg-base-200 flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl">
                                            <div class="text-[8px] font-black uppercase opacity-40">S</div>
                                            <div class="text-sm font-black leading-none">{{ $matchDayMap[$match['week_number']] ?? $match['week_number'] }}</div>
                                        </div>

                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($match['is_home'])
                                                    <x-badge class="badge-neutral badge-xs font-bold" value="{{ __('Home') }}" />
                                                @else
                                                    <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" value="{{ __('Away') }}" />
                                                @endif
                                                <span class="font-bold">vs {{ $match['opponent'] }}</span>
                                                @if ($match['is_selected'])
                                                    <x-badge class="badge-primary badge-sm font-bold"
                                                        value="{{ __('Selected') }}" icon="o-check" />
                                                @endif
                                                @if ($urgency)
                                                    <x-badge class="badge-warning badge-sm animate-pulse font-bold"
                                                        value="{{ __('Response needed') }}" />
                                                @endif
                                            </div>
                                            <div class="text-base-content/50 mt-0.5 flex items-center gap-2 text-xs">
                                                <x-icon name="o-calendar" class="h-3 w-3" />
                                                {{ $match['date'] }} {{ __('at') }} {{ $match['time'] }}
                                                <span class="opacity-50">·</span>
                                                <x-icon name="o-map-pin" class="h-3 w-3" />
                                                <span class="truncate">{{ $match['address'] }}</span>
                                            </div>
                                            @if ($match['availability_note'])
                                                <div class="text-base-content/40 mt-1 text-[11px] italic">
                                                    "{{ $match['availability_note'] }}"
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Contrôles disponibilité --}}
                                    <div class="flex shrink-0 items-center gap-2">
                                        @if ($avail !== null)
                                            <x-badge :class="$avail->color() . ' badge-sm font-bold'"
                                                :value="$avail->label()" />
                                        @else
                                            <span
                                                class="text-base-content/30 text-[11px] font-bold uppercase tracking-wide">{{ __('No response') }}</span>
                                        @endif

                                        <x-dropdown>
                                            <x-slot:trigger>
                                                <x-button
                                                    @class([
                                                        'btn-sm btn-ghost border-base-300 border',
                                                        'border-success/40 bg-success/5' =>
                                                            $avail === \App\Domains\Shared\Enums\InterclubAvailability::AVAILABLE,
                                                        'border-warning/40 bg-warning/5' =>
                                                            $avail === \App\Domains\Shared\Enums\InterclubAvailability::MAYBE,
                                                        'border-error/40 bg-error/5' =>
                                                            $avail === \App\Domains\Shared\Enums\InterclubAvailability::UNAVAILABLE,
                                                    ])
                                                    icon="o-chevron-down"
                                                    :label="$avail ? $avail->label() : __('Set availability')" />
                                            </x-slot:trigger>

                                            <x-menu-item
                                                wire:click="markAvailability({{ $match['id'] }}, '{{ \App\Domains\Shared\Enums\InterclubAvailability::AVAILABLE->value }}')"
                                                :title="__('Available')"
                                                icon="o-check-circle"
                                                class="text-success" />

                                            <x-menu-item
                                                wire:click="markAvailability({{ $match['id'] }}, '{{ \App\Domains\Shared\Enums\InterclubAvailability::MAYBE->value }}')"
                                                :title="__('Maybe')"
                                                icon="o-question-mark-circle"
                                                class="text-warning-content" />

                                            <x-menu-item
                                                wire:click="markAvailability({{ $match['id'] }}, '{{ \App\Domains\Shared\Enums\InterclubAvailability::UNAVAILABLE->value }}')"
                                                :title="__('Unavailable')"
                                                icon="o-x-circle"
                                                class="text-error" />

                                            <x-menu-separator />

                                            <x-menu-item
                                                wire:click="openNote({{ $match['id'] }})"
                                                :title="__('Add a note')"
                                                icon="o-pencil-square" />
                                        </x-dropdown>
                                    </div>
                                </div>

                                {{-- Note inline --}}
                                @if ($editingInterclubId === $match['id'])
                                    <div class="border-base-200 mt-3 flex items-center gap-2 border-t pt-3">
                                        <x-input class="input-sm flex-1 bg-base-200/50 border-none rounded-lg"
                                            :placeholder="__('Optional note for your captain...')"
                                            wire:model="availabilityNote" />
                                        <x-button class="btn-primary btn-sm"
                                            icon="o-check"
                                            :label="__('Save')"
                                            wire:click="markAvailability({{ $match['id'] }}, '{{ $avail?->value ?? \App\Domains\Shared\Enums\InterclubAvailability::AVAILABLE->value }}')" />
                                        <x-button class="btn-ghost btn-sm"
                                            icon="o-x-mark"
                                            wire:click="$set('editingInterclubId', null)" />
                                    </div>
                                @endif
                            </div>
                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <x-confirm-modal model="bulkUnavailableModal" :title="__('Mark all as unavailable?')"
        :confirmLabel="__('Confirm')" confirmClass="btn-error" confirmAction="bulkMarkUnavailable">
        <p>{{ __('This will mark all your upcoming matches as unavailable. This action can be undone match by match.') }}</p>
    </x-confirm-modal>
</div>
