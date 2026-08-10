{{--
    Carte de statistique de l'admin — un chiffre clé, partout pareil.

    Une pastille d'icône colorée à gauche, puis eyebrow + valeur + précision.

    Usage :
        <x-admin.shared.stat-card :label="$label" value="1 250,00 €"
            :hint="$hint" icon="o-clock" color="warning" />

    `value` arrive déjà formaté (montants en €, compteurs) : le formatage reste
    chez l'appelant, qui seul sait s'il s'agit d'un euro ou d'un décompte.

    La couleur porte le sens via la pastille, jamais via le chiffre : une grille de
    chiffres colorés se lit mal. Le chiffre garde la couleur de texte par défaut.

    L'icône utilise les tokens `-content` (L≈37-41 %) et non les tokens de base
    (L≈70-82 %), qui ne passent pas le contraste sur une surface claire. Voir
    `resources/css/app.css:105-110`, et le commentaire ligne 134 qui documente
    l'intention pour le thème sombre.

    L'eyebrow et la précision s'atténuent par la couleur (`.text-muted`,
    `.text-subtle`), jamais par `opacity-*` : une opacité ne se mesure pas et
    échappe à la sonde de contraste. L'eyebrow y perdait 3,43:1 sur cinq écrans.

    La précision passe à la ligne au lieu d'être tronquée : coupée en plein mot
    (« 0 paiemen… ») elle n'apprend rien, et la grille aligne de toute façon les
    cartes d'une même rangée sur la plus haute.

    Les attributs de l'appelant sont transmis par le sac (`$attributes->class()`).
    Interpoler `{{ $attributes->get('class') }}` dans un attribut de balise de
    composant ne marche pas — Blade coupe l'expression sur `->` et l'appelant
    perd sa classe (`col-span-2` était muet sur quatre écrans).

    Purement informatif : le filtrage passe par les onglets, jamais par la carte.
--}}
@props([
    'label',
    'value',
    'hint' => null,
    'icon' => null,
    'color' => 'neutral',
    'emphasis' => false,
])

@php
    [$chipClasses, $iconClasses] = match ($color) {
        'success' => ['bg-success/10', 'text-success-content'],
        'warning' => ['bg-warning/10', 'text-warning-content'],
        'error' => ['bg-error/10', 'text-error-content'],
        'primary' => ['bg-primary/10', 'text-primary'],
        default => ['bg-base-200', 'text-base-content/60'],
    };
@endphp

<x-card data-stat-card {{ $attributes->class('shadow-sm') }}>
    <div class="flex items-center gap-3">
        @if ($icon)
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $chipClasses }}">
                <x-icon :name="$icon" class="h-5 w-5 {{ $iconClasses }}" />
            </div>
        @endif
        <div class="min-w-0">
            <div class="text-xs font-bold uppercase tracking-widest text-muted">{{ $label }}</div>
            <div data-stat-value class="{{ $emphasis ? 'text-3xl' : 'text-2xl' }} font-black tabular-nums">{{ $value }}</div>
            @if ($hint)
                <div data-stat-hint class="mt-0.5 text-xs text-subtle">{{ $hint }}</div>
            @endif
        </div>
    </div>
</x-card>
