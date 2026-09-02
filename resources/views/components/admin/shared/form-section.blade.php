@props(['title', 'subtitle', 'separator' => true, 'stacked' => false])

{{--
    The section owns its two-column layout instead of borrowing one. It used to
    emit bare col-span-* children and rely on an ancestor grid: the settings page
    was once wrapped in <x-form>, whose plain `grid` created the implicit tracks
    those spans needed. That wrapper went away in 173af8e5 and every section has
    stacked since, on both pages using this component.
--}}
{{--
    `stacked` place le titre au-dessus des champs au lieu d'en prendre le tiers
    gauche. C'est ce qu'il faut quand la section vit déjà dans une colonne
    étroite -- l'étape 1 de l'assistant, qui partage sa largeur avec le
    simulateur. Sans l'option, le rendu ne bouge pas.
--}}
<div @class(['grid grid-cols-1 gap-x-8 gap-y-4', 'md:grid-cols-6' => ! $stacked])>
    <div @class(['md:col-span-2' => ! $stacked])>
        <x-header :title="$title" :subtitle="$subtitle" size="md" />
    </div>

    <div @class(['grid gap-2', 'md:col-span-4' => ! $stacked])>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{ $slot }}
        </div>
    </div>
</div>

@if ($separator === true)
    <x-menu-separator />
@endif
