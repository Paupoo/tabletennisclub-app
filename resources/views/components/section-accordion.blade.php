@props([
    'label'     => '',
    'count'     => null,
    'color'     => 'blue',
    'open'      => true,
    'uppercase' => true,
    // Opt-in, so the ten views already using this component keep the behaviour
    // they were written against: pass false and the section arrives folded on
    // anything narrower than the sidebar breakpoint.
    'openOnMobile' => true,
    'wireToggle'   => null,
])

{{-- Folding is Alpine's business by default: it costs nothing and the content
     is in the page either way. `wireToggle` is for the sections where that is
     the problem — a couple of hundred cards nobody asked to see, rebuilt on
     every render. Given one, the server owns the fold: the slot is not rendered
     at all while closed, and the button asks Livewire rather than Alpine. --}}

@php
    /* Les deux thèmes portent le même poids perçu, pas la même valeur.
       En clair la pastille tient par sa bordure (ΔL 8,5 sur le fond) et son
       aplat ne pèse rien (ΔL 0,03) ; le premier jet sombre gardait ce rôle mais
       en doublait tout — bordure -800 à ΔL 21,2 et chroma 0,19, soit une bague
       saturée là où le clair pose un liseré. Les valeurs sombres sont donc
       calées sur le poids du clair, toutes ancrées sur la nuance -900 :
       aplat /15 (ΔL ≈ 2,5), bordure /50 (ΔL ≈ 9), filet /25 (ΔL ≈ 4). */
    $colors = [
        'blue'    => ['pill_bg' => 'bg-blue-50 dark:bg-blue-900/15', 'pill_border' => 'border-blue-200 dark:border-blue-900/50', 'pill_text' => 'text-blue-700 dark:text-blue-300', 'dot' => 'bg-blue-500', 'sep' => 'border-blue-100 dark:border-blue-900/25'],
        'amber'   => ['pill_bg' => 'bg-amber-50 dark:bg-amber-900/15', 'pill_border' => 'border-amber-200 dark:border-amber-900/50', 'pill_text' => 'text-amber-700 dark:text-amber-300', 'dot' => 'bg-amber-500', 'sep' => 'border-amber-100 dark:border-amber-900/25'],
        'rose'    => ['pill_bg' => 'bg-rose-50 dark:bg-rose-900/15', 'pill_border' => 'border-rose-200 dark:border-rose-900/50', 'pill_text' => 'text-rose-700 dark:text-rose-300', 'dot' => 'bg-rose-500', 'sep' => 'border-rose-100 dark:border-rose-900/25'],
        'violet'  => ['pill_bg' => 'bg-violet-50 dark:bg-violet-900/15', 'pill_border' => 'border-violet-200 dark:border-violet-900/50', 'pill_text' => 'text-violet-700 dark:text-violet-300', 'dot' => 'bg-violet-500', 'sep' => 'border-violet-100 dark:border-violet-900/25'],
        'emerald' => ['pill_bg' => 'bg-emerald-50 dark:bg-emerald-900/15', 'pill_border' => 'border-emerald-200 dark:border-emerald-900/50', 'pill_text' => 'text-emerald-700 dark:text-emerald-300', 'dot' => 'bg-emerald-500', 'sep' => 'border-emerald-100 dark:border-emerald-900/25'],
        'pink'    => ['pill_bg' => 'bg-pink-50 dark:bg-pink-900/15', 'pill_border' => 'border-pink-200 dark:border-pink-900/50', 'pill_text' => 'text-pink-700 dark:text-pink-300', 'dot' => 'bg-pink-500', 'sep' => 'border-pink-100 dark:border-pink-900/25'],
        'gray'    => ['pill_bg' => 'bg-base-200 dark:bg-base-300/20', 'pill_border' => 'border-base-300 dark:border-base-300/50', 'pill_text' => 'text-base-content/60', 'dot' => 'bg-base-content/30', 'sep' => 'border-base-200 dark:border-base-300/25'],
    ];

    $c = $colors[$color] ?? $colors['gray'];

    /* Read once, when Alpine initialises the section: rotating a tablet does not
       refold what the reader has since opened, which is the behaviour we want —
       a resize is not a reason to take a panel away from someone reading it. */
    $initialState = $open && ! $openOnMobile
        ? "{ open: window.matchMedia('(min-width: 1024px)').matches }"
        : json_encode(['open' => $open]);
@endphp

<section @if ($wireToggle === null)x-data="{{ $initialState }}" @endif{{ $attributes }}>

    {{-- `group` + `cursor-pointer` : Tailwind v4 a retiré du preflight le
         `button { cursor: pointer }` que v3 posait, et rien ne le remplace ici
         (les deux seules règles du bundle sont scopées, `.modal-backdrop` et
         `.mary-table-pagination`). Un `<button>` nu avait donc le curseur d'un
         texte inerte : la section se lisait comme un badge, pas comme une
         commande, et personne ne cliquait. --}}
    <button type="button"
        class="group mb-3 flex w-full cursor-pointer items-center gap-3 text-left focus-visible:outline-none"
        @if ($wireToggle === null)
            :aria-expanded="open ? 'true' : 'false'"
            @click="open = !open"
        @else
            aria-expanded="{{ $open ? 'true' : 'false' }}"
            wire:click="{{ $wireToggle }}"
        @endif>

        {{-- L'anneau remplace un aplat de survol : il se pose hors du flux, donc
             il vaut pour les sept palettes et les deux thèmes sans toucher à la
             mise en page. Le même anneau sert d'indicateur de focus, en primaire,
             à la place du contour natif qui cerclait toute la largeur du bouton. --}}
        <span class="inline-flex shrink-0 items-center gap-2 rounded-full {{ $c['pill_bg'] }} {{ $c['pill_border'] }} border px-4 py-1.5 transition group-hover:ring-2 group-hover:ring-base-content/10 group-focus-visible:ring-2 group-focus-visible:ring-primary/60">
            <span class="h-2 w-2 rounded-full {{ $c['dot'] }}"></span>
            <span @class(['text-sm font-bold', $c['pill_text'], 'uppercase tracking-wide' => $uppercase])>{{ $label }}</span>
            @if($count !== null)
                {{-- Le compteur se distingue du libellé par la taille et la graisse.
                     Une opacité en plus retombait sous 2,5:1, la couleur de pastille
                     portant déjà son propre alpha. --}}
                <span class="text-xs {{ $c['pill_text'] }}">{{ $count }}</span>
            @endif
            @isset($suffix){{ $suffix }}@endisset
        </span>

        {{-- Le chevron se tient contre la pastille, pas au bout du filet : c'est
             le mécanisme du <summary> natif, le marqueur colle aux mots. À
             l'autre extrémité il pouvait être à mille pixels de son libellé sur
             un écran admin large, et les quatre sections qui arrivent pliées
             n'avaient plus rien pour se signaler. `/30` valait 1,96:1 (DS-B) :
             seul indice d'affordance du composant, il passe a `/60`. --}}

        {{-- Two tags rather than one carrying a conditional attribute: Blade
             compiles a component by matching its tag, and a @if between its
             attributes leaves it uncompiled — the icon would render as its own
             source text. --}}
        @if ($wireToggle === null)
            <x-icon name="o-chevron-down"
                class="h-4 w-4 shrink-0 text-base-content/60 transition-transform duration-200 group-hover:text-base-content"
                ::class="open ? '' : '-rotate-90'" />
        @else
            <x-icon name="o-chevron-down" @class([
                'h-4 w-4 shrink-0 text-base-content/60 transition-transform duration-200 group-hover:text-base-content',
                '-rotate-90' => ! $open,
            ]) />
        @endif

        <div class="flex-1 border-t {{ $c['sep'] }}"></div>

    </button>

    @if ($wireToggle === null)
        <div x-show="open" x-collapse>
            {{ $slot }}
        </div>
    @elseif ($open)
        <div>{{ $slot }}</div>
    @endif

</section>
