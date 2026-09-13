@props([
    'key',
    'label',
    'icon' => 'o-cube',
    'iconClass' => 'text-primary',
    'count' => 0,
])

{{--
    Un rayon du catalogue, repliable.

    Alpine et non `<details>` natif : Livewire remorphe la page à chaque « + », et le
    serveur ne rend pas l'attribut `open`, si bien que le morph le retirerait — tous
    les rayons se refermeraient à chaque article ajouté. L'état vit donc côté client,
    sur un élément porteur d'un `wire:key`, que Livewire préserve.

    Il est mémorisé d'un service à l'autre : un point de vente qui démarre entièrement
    replié n'affiche qu'une pile de barres grises, et il faut un tap par rayon avant de
    pouvoir servir quoi que ce soit. Sans préférence connue, le rayon s'ouvre.
--}}
<div
    wire:key="panel-{{ $key }}"
    x-data="{
        open: true,
        init() {
            const stored = localStorage.getItem('bar_panel_{{ $key }}');
            this.open = stored === null ? true : stored === 'true';
            this.$watch('open', value => localStorage.setItem('bar_panel_{{ $key }}', value));
        },
    }"
    class="border-base-300 bg-base-100 rounded-xl border"
>
    <button
        type="button"
        @click="open = ! open"
        :aria-expanded="open ? 'true' : 'false'"
        class="tap-comfort w-full cursor-pointer justify-start gap-2.5 px-4 py-3 text-sm font-bold"
    >
        <x-icon :name="$icon" class="h-5 w-5 {{ $iconClass }}" />
        {{ $label }}
        <span class="badge badge-ghost badge-sm ms-auto font-bold tabular-nums">{{ $count }}</span>
        <x-icon name="o-chevron-down"
            class="text-base-content/50 h-4 w-4 transition-transform"
            ::class="open && 'rotate-180'" />
    </button>

    <div x-show="open" x-collapse class="border-base-300 border-t lg:grid lg:grid-cols-2">
        {{ $slot }}
    </div>
</div>
