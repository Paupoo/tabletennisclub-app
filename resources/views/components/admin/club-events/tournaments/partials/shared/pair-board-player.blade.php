{{--
    Un joueur sur le tableau des paires.
    Attend : $player (id, name, ranking, age), $locked (bool).
    Glisser-déposer à la souris ; au doigt, toucher ce joueur puis sa cible.
--}}
<div
    wire:key="pair-player-{{ $player['id'] }}"
    @if (! $locked)
        draggable="true"
        role="button"
        tabindex="0"
        @dragstart="dragging = {{ $player['id'] }}; $event.dataTransfer.effectAllowed = 'move'"
        @dragend="dragging = null; over = null"
        @dragover.prevent.stop="over = {{ $player['id'] }}"
        @dragleave="over === {{ $player['id'] }} && (over = null)"
        @drop.prevent.stop="drop({{ $player['id'] }})"
        @click="pick({{ $player['id'] }})"
        @keydown.enter.prevent="pick({{ $player['id'] }})"
        :class="{
            'ring-2 ring-primary': selected === {{ $player['id'] }} || over === {{ $player['id'] }},
            'opacity-40': dragging === {{ $player['id'] }},
        }"
    @endif
    class="flex min-w-0 items-center gap-2 rounded-lg bg-base-100 px-2 py-1.5 text-sm {{ $locked ? '' : 'cursor-grab active:cursor-grabbing' }}">
    @unless ($locked)
        <x-icon name="o-bars-2" class="h-3.5 w-3.5 shrink-0 text-base-content/40" />
    @endunless
    <span class="min-w-0 flex-1 truncate font-medium">{{ $player['name'] }}</span>
    <span class="shrink-0 rounded bg-base-200 px-1.5 py-0.5 text-xs font-semibold">{{ $player['ranking'] }}</span>
    <span class="w-14 shrink-0 text-right text-xs text-base-content/70">
        {{ $player['age'] === null ? '—' : __(':age years old', ['age' => $player['age']]) }}
    </span>
</div>
