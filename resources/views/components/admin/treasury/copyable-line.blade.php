{{--
    Une valeur que le trésorier va recopier dans sa banque.

    Le bouton est du HTML natif, pas un `<x-button>` : Blade n'exécute pas ses
    directives dans l'attribut d'une balise de composant, si bien qu'un
    `@click="…writeText(@js($value))"` arrivait tel quel dans la page et ne
    produisait qu'une erreur de syntaxe silencieuse. La valeur est donc lue dans
    le DOM (`$refs.value`) plutôt qu'interpolée dans du JavaScript — rien à
    échapper, et une apostrophe dans un nom ne casse plus rien.

    `wrap` pour la communication SEPA : tronquée, elle ne se copie pas, et c'est
    son seul usage.
--}}
@props([
    'label',
    'value',
    'copyable' => true,
    'mono' => false,
    'wrap' => false,
])

<div class="rounded-lg border border-base-300 bg-base-200/40 p-3" x-data="{ copied: false }">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ $label }}</p>
            <p x-ref="value" @class([
                'mt-0.5 text-sm',
                'font-mono' => $mono,
                'break-all' => $wrap,
                'font-semibold' => ! $wrap,
            ])>{{ $value }}</p>
        </div>

        @if ($copyable && filled($value))
            <button
                type="button"
                class="btn btn-xs btn-ghost shrink-0"
                :class="copied && 'btn-success'"
                @click="navigator.clipboard.writeText($refs.value.textContent.trim()).then(() => {
                    copied = true;
                    setTimeout(() => copied = false, 1500);
                })">
                <x-icon name="o-clipboard-document" class="h-3.5 w-3.5" />
                <span x-show="! copied">{{ __('Copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
            </button>
        @endif
    </div>
</div>
