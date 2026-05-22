<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header title="{{ __('My Matches') }}" subtitle="{{ __('Manage your availability for upcoming interclubs') }}"
        separator />

    @if ($grouped->isEmpty())
        <x-empty-state icon="o-calendar" title="{{ __('No upcoming matches') }}"
            description="{{ __('You are not registered in any team for this season.') }}" />
    @else
        <div class="space-y-8">
            @foreach ($grouped as $teamName => $matches)
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
                                            <div class="text-[8px] font-black uppercase opacity-40">WK</div>
                                            <div class="text-sm font-black leading-none">{{ $match['week_number'] }}</div>
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
                                                            $avail === \App\Enums\InterclubAvailability::AVAILABLE,
                                                        'border-warning/40 bg-warning/5' =>
                                                            $avail === \App\Enums\InterclubAvailability::MAYBE,
                                                        'border-error/40 bg-error/5' =>
                                                            $avail === \App\Enums\InterclubAvailability::UNAVAILABLE,
                                                    ])
                                                    icon="o-chevron-down"
                                                    :label="$avail ? $avail->label() : __('Set availability')" />
                                            </x-slot:trigger>

                                            <x-menu-item
                                                wire:click="markAvailability({{ $match['id'] }}, '{{ \App\Enums\InterclubAvailability::AVAILABLE->value }}')"
                                                title="{{ __('Available') }}"
                                                icon="o-check-circle"
                                                class="text-success" />

                                            <x-menu-item
                                                wire:click="markAvailability({{ $match['id'] }}, '{{ \App\Enums\InterclubAvailability::MAYBE->value }}')"
                                                title="{{ __('Maybe') }}"
                                                icon="o-question-mark-circle"
                                                class="text-warning" />

                                            <x-menu-item
                                                wire:click="markAvailability({{ $match['id'] }}, '{{ \App\Enums\InterclubAvailability::UNAVAILABLE->value }}')"
                                                title="{{ __('Unavailable') }}"
                                                icon="o-x-circle"
                                                class="text-error" />

                                            <x-menu-separator />

                                            <x-menu-item
                                                wire:click="openNote({{ $match['id'] }})"
                                                title="{{ __('Add a note') }}"
                                                icon="o-pencil-square" />
                                        </x-dropdown>
                                    </div>
                                </div>

                                {{-- Note inline --}}
                                @if ($editingInterclubId === $match['id'])
                                    <div class="border-base-200 mt-3 flex items-center gap-2 border-t pt-3">
                                        <x-input class="input-sm flex-1 bg-base-200/50 border-none rounded-lg"
                                            placeholder="{{ __('Optional note for your captain...') }}"
                                            wire:model="availabilityNote" />
                                        <x-button class="btn-primary btn-sm"
                                            icon="o-check"
                                            label="{{ __('Save') }}"
                                            wire:click="markAvailability({{ $match['id'] }}, '{{ $avail?->value ?? \App\Enums\InterclubAvailability::AVAILABLE->value }}')" />
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
    @endif
</div>
