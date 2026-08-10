@props([
    'label'     => '',
    'count'     => null,
    'color'     => 'blue',
    'open'      => true,
    'uppercase' => true,
])

@php
    $colors = [
        'blue'    => ['pill_bg' => 'bg-blue-50   dark:bg-blue-950/40',   'pill_border' => 'border-blue-200   dark:border-blue-800',   'pill_text' => 'text-blue-700   dark:text-blue-300',   'dot' => 'bg-blue-500',   'sep' => 'border-blue-100   dark:border-blue-900/50'],
        'amber'   => ['pill_bg' => 'bg-amber-50  dark:bg-amber-950/40',  'pill_border' => 'border-amber-200  dark:border-amber-800',  'pill_text' => 'text-amber-700  dark:text-amber-300',  'dot' => 'bg-amber-500',  'sep' => 'border-amber-100  dark:border-amber-900/50'],
        'rose'    => ['pill_bg' => 'bg-rose-50   dark:bg-rose-950/40',   'pill_border' => 'border-rose-200   dark:border-rose-800',   'pill_text' => 'text-rose-700   dark:text-rose-300',   'dot' => 'bg-rose-500',   'sep' => 'border-rose-100   dark:border-rose-900/50'],
        'violet'  => ['pill_bg' => 'bg-violet-50 dark:bg-violet-950/40', 'pill_border' => 'border-violet-200 dark:border-violet-800', 'pill_text' => 'text-violet-700 dark:text-violet-300', 'dot' => 'bg-violet-500', 'sep' => 'border-violet-100 dark:border-violet-900/50'],
        'emerald' => ['pill_bg' => 'bg-emerald-50 dark:bg-emerald-950/40','pill_border' => 'border-emerald-200 dark:border-emerald-800','pill_text' => 'text-emerald-700 dark:text-emerald-300','dot' => 'bg-emerald-500','sep' => 'border-emerald-100 dark:border-emerald-900/50'],
        'pink'    => ['pill_bg' => 'bg-pink-50   dark:bg-pink-950/40',   'pill_border' => 'border-pink-200   dark:border-pink-800',   'pill_text' => 'text-pink-700   dark:text-pink-300',   'dot' => 'bg-pink-500',   'sep' => 'border-pink-100   dark:border-pink-900/50'],
        'indigo'  => ['pill_bg' => 'bg-indigo-50 dark:bg-indigo-950/40', 'pill_border' => 'border-indigo-200 dark:border-indigo-800', 'pill_text' => 'text-indigo-700 dark:text-indigo-300', 'dot' => 'bg-indigo-500', 'sep' => 'border-indigo-100 dark:border-indigo-900/50'],
        'gray'    => ['pill_bg' => 'bg-base-200  dark:bg-base-300/20',   'pill_border' => 'border-base-300',                          'pill_text' => 'text-base-content/60',                  'dot' => 'bg-base-content/30','sep' => 'border-base-200'],
    ];
    $c = $colors[$color] ?? $colors['gray'];
@endphp

<section x-data="{{ json_encode(['open' => $open]) }}" {{ $attributes }}>

    <button type="button"
        class="mb-3 flex w-full items-center gap-3 text-left"
        :aria-expanded="open ? 'true' : 'false'"
        @click="open = !open">

        <span class="inline-flex shrink-0 items-center gap-2 rounded-full {{ $c['pill_bg'] }} {{ $c['pill_border'] }} border px-4 py-1.5">
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

        <div class="flex-1 border-t {{ $c['sep'] }}"></div>

        <x-icon name="o-chevron-down"
            class="h-4 w-4 text-base-content/30 transition-transform duration-200"
            ::class="open ? '' : '-rotate-90'" />

    </button>

    <div x-show="open" x-collapse>
        {{ $slot }}
    </div>

</section>
