@php
    /**
     * Colour on a dashboard tile carries URGENCY, never domain.
     *
     * The club owns two colours — club blue and club yellow — over a neutral
     * canvas. A grid where every domain wears its own hue encodes nothing a
     * reader can decode: there is no legend, and "Saisons violet, Réunions
     * ambre" tells nobody anything. So the rule is the opposite one:
     *
     *   neutral   — nothing is waiting on you (the default, and most tiles)
     *   primary   — something is waiting on you (club blue)
     *   secondary — the one entry a persona must find first (club yellow)
     *
     * A tile carrying a badge is waiting on someone by definition, so it takes
     * the club blue whatever it asked for. Anything else falls back to neutral
     * rather than inventing a colour.
     *
     * Kept in step with the design-system kit:
     * `.claude/skills/CTT Ottignies-Blocry Design System/components/admin/DashboardTile.jsx`.
     */
    $colorMap = [
        'primary'   => ['border' => 'hover:border-primary',   'icon_bg' => 'bg-primary/10',   'icon_text' => 'text-primary'],
        'secondary' => ['border' => 'hover:border-secondary', 'icon_bg' => 'bg-secondary/20', 'icon_text' => 'text-secondary-content'],
        'neutral'   => ['border' => 'hover:border-primary',   'icon_bg' => 'bg-base-200',     'icon_text' => 'text-base-content/60'],
    ];

    $pending = ! empty($badge) && $badge > 0;
    $c = $colorMap[$pending ? 'primary' : ($color ?? 'neutral')] ?? $colorMap['neutral'];
@endphp

<a href="{{ $href ?? '#' }}"
   class="group relative bg-base-100 rounded-xl border border-base-300 {{ $c['border'] }} hover:shadow-md transition-all p-4 flex flex-col items-center gap-2 text-center">

    @if($pending)
    <span class="absolute top-2 right-2">
        <x-badge value="{{ $badge }}" class="badge-error badge-xs" />
    </span>
    @endif

    <div class="{{ $c['icon_bg'] }} {{ $c['icon_text'] }} rounded-xl p-2.5 group-hover:scale-110 transition-transform">
        <x-icon name="{{ $icon }}" class="w-5 h-5" />
    </div>
    <span class="text-xs font-semibold text-base-content leading-tight">{{ $label }}</span>
    <span class="text-xs text-base-content/40 leading-tight">{{ $sub }}</span>
</a>
