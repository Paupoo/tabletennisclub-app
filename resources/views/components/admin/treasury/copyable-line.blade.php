{{--
    Une valeur que le trésorier va recopier dans sa banque.

    Le bouton copie porte un nom, pas seulement une icône : « bouton, bouton »
    est ce qu'un lecteur d'écran annonçait sur les actions de ligne avant
    b42fdef7, et une modale qui sert à copier trois choses ne peut pas les
    laisser anonymes.

    `wrap` pour la communication SEPA : 140 caractères tronqués ne se copient
    pas, et c'est le seul usage de cette chaîne.
--}}
@props([
    'label',
    'value',
    'hint' => null,
    'copyable' => true,
    'mono' => false,
    'wrap' => false,
])

<div class="rounded-lg border border-base-300 bg-base-200/40 p-3">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ $label }}</p>
            <p @class([
                'mt-0.5 text-sm',
                'font-mono' => $mono,
                'break-all' => $wrap,
                'font-semibold' => ! $wrap,
            ])>{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
            @endif
        </div>

        @if ($copyable && filled($value))
            <x-button
                :label="__('Copy')"
                icon="o-clipboard-document"
                class="btn-xs btn-ghost shrink-0"
                x-data
                @click="navigator.clipboard.writeText(@js($value)); $el.classList.add('btn-success')" />
        @endif
    </div>
</div>
