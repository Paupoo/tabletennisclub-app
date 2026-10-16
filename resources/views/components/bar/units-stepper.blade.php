@props(['model', 'label', 'packSize' => 1, 'packLabel' => null])

@php
    $packSize = max(1, (int) $packSize);
    $packName = $packLabel !== null && $packLabel !== '' ? $packLabel : (string) $packSize;
@endphp

{{--
    Un compteur d'unités, − / +, pensé pour le pouce.

    On compte des unités parce que le magasin n'a pas toujours le conditionnement
    prévu : on tape 6 quand il n'y avait qu'un pack de 6. Les boutons restent le
    raccourci du cas courant et font toujours la même chose — un conditionnement
    entier de plus ou de moins (6 → 14), jamais sous zéro. Le repère dessous dit
    ce que le nombre représente.

    Les boutons agissent sur le champ dans le navigateur et lui envoient un
    `input` : `wire:model` (différé) le reprend à la prochaine action, sans un
    aller-retour au serveur par tap.
--}}
<div class="shrink-0 text-right"
    x-data="{
        units: 0,
        step(by) {
            const field = this.$refs.units;
            field.value = Math.max(0, (parseInt(field.value, 10) || 0) + by);
            field.dispatchEvent(new Event('input'));
        },
        get hint() {
            return {{ $packSize }} > 1 && this.units > 0 && this.units % {{ $packSize }} === 0
                ? (this.units / {{ $packSize }}) + ' × ' + {{ Js::from($packName) }}
                : {{ Js::from(__(':units units', ['units' => '__UNITS__'])) }}.replace('__UNITS__', this.units);
        },
    }"
    x-init="$nextTick(() => units = parseInt($refs.units.value, 10) || 0)">
    <div class="join">
        <button type="button" class="join-item btn btn-sm tap-min" aria-label="{{ __('One pack less: :product', ['product' => $label]) }}"
            @click="step(-{{ $packSize }})">−</button>
        <input x-ref="units" type="number" min="0" inputmode="numeric" wire:model="{{ $model }}"
            @input="units = parseInt($event.target.value, 10) || 0"
            aria-label="{{ __('Units bought: :product', ['product' => $label]) }}"
            class="join-item input input-sm w-16 text-center tabular-nums">
        <button type="button" class="join-item btn btn-sm tap-min" aria-label="{{ __('One pack more: :product', ['product' => $label]) }}"
            @click="step({{ $packSize }})">+</button>
    </div>
    <p class="text-subtle mt-0.5 text-xs tabular-nums" x-text="hint" aria-live="polite"></p>
</div>
