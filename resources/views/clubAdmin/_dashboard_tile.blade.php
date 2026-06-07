@php
    $colorMap = [
        'primary'   => ['border' => 'hover:border-primary/30',   'icon_bg' => 'bg-primary/10',   'icon_text' => 'text-primary'],
        'secondary' => ['border' => 'hover:border-secondary/30', 'icon_bg' => 'bg-secondary/10', 'icon_text' => 'text-secondary'],
        'error'     => ['border' => 'hover:border-error/30',     'icon_bg' => 'bg-error/10',     'icon_text' => 'text-error'],
        'neutral'   => ['border' => 'hover:border-base-300',     'icon_bg' => 'bg-base-200',     'icon_text' => 'text-base-content/50'],
    ];
    $c = $colorMap[$color ?? 'neutral'];
@endphp

<a href="{{ $href ?? '#' }}"
   class="group relative bg-base-100 rounded-xl border border-base-200 {{ $c['border'] }} hover:shadow-md transition-all p-4 flex flex-col items-center gap-2 text-center">

    @if(!empty($badge) && $badge > 0)
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
