{{-- Les pastilles de journée. Un seul état, `selectedMatchDay`, pour les deux
     endroits qui les montrent : la journée qu'on regarde est la même, qu'on
     cherche un renfort ou qu'on ouvre la rencontre qui en manque. --}}
@php
    $dayIndex = array_search($selectedMatchDay, $matchDays, true);
    $prevDay = $dayIndex !== false && $dayIndex > 0 ? $matchDays[$dayIndex - 1] : null;
    $nextDay = $dayIndex !== false && $dayIndex < count($matchDays) - 1 ? $matchDays[$dayIndex + 1] : null;
@endphp

@if (count($matchDays) > 1)
    <div class="flex items-center gap-2">
        <x-button class="btn-sm btn-ghost border border-base-300" icon="o-chevron-left"
            :disabled="$prevDay === null"
            :aria-label="__('Previous match day')"
            wire:click="selectDay({{ $prevDay ?? 'null' }})" />
        <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 py-0.5">
            @foreach ($matchDays as $day)
                <button type="button" wire:click="selectDay({{ $day }})"
                    @if ($day === $selectedMatchDay) aria-current="true" @endif
                    @class([
                        'shrink-0 cursor-pointer rounded-full border px-3 py-1 text-sm font-bold tabular-nums transition-colors',
                        'border-primary bg-primary/10 text-primary' => $day === $selectedMatchDay,
                        'border-base-300 text-base-content/70 hover:border-primary/50' => $day !== $selectedMatchDay,
                    ])>{{ $matchDayMap[$day] ?? $day }}</button>
            @endforeach
        </div>
        <x-button class="btn-sm btn-ghost border border-base-300" icon="o-chevron-right"
            :disabled="$nextDay === null"
            :aria-label="__('Next match day')"
            wire:click="selectDay({{ $nextDay ?? 'null' }})" />
    </div>
@endif
