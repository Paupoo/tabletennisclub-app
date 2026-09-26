@props(['model', 'label'])

{{--
    Un compteur de conditionnements, − / +, pensé pour le pouce.

    Les boutons agissent sur le champ dans le navigateur et lui envoient un
    `input` : `wire:model` (différé) le reprend à la prochaine action, sans un
    aller-retour au serveur par tap.
--}}
<div x-data class="join shrink-0">
    <button type="button" class="join-item btn btn-sm tap-min" aria-label="{{ __('One less: :product', ['product' => $label]) }}"
        @click="$refs.packs.stepDown(); $refs.packs.dispatchEvent(new Event('input'))">−</button>
    <input x-ref="packs" type="number" min="0" inputmode="numeric" wire:model="{{ $model }}"
        aria-label="{{ __('Packs bought: :product', ['product' => $label]) }}"
        class="join-item input input-sm w-14 text-center tabular-nums">
    <button type="button" class="join-item btn btn-sm tap-min" aria-label="{{ __('One more: :product', ['product' => $label]) }}"
        @click="$refs.packs.stepUp(); $refs.packs.dispatchEvent(new Event('input'))">+</button>
</div>
