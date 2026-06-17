@php
    $colorMap = [
        // daisyUI semantic aliases
        'primary'   => ['border' => 'hover:border-primary/30',    'icon_bg' => 'bg-primary/10',    'icon_text' => 'text-primary'],
        'secondary' => ['border' => 'hover:border-secondary/30',  'icon_bg' => 'bg-secondary/10',  'icon_text' => 'text-secondary'],
        'error'     => ['border' => 'hover:border-error/30',      'icon_bg' => 'bg-error/10',      'icon_text' => 'text-error'],
        'neutral'   => ['border' => 'hover:border-base-300',      'icon_bg' => 'bg-base-200',      'icon_text' => 'text-base-content/50'],
        // Named palette — mirrors DS DashboardTile.jsx colorMap
        'blue'      => ['border' => 'hover:border-blue-200',      'icon_bg' => 'bg-blue-100',      'icon_text' => 'text-blue-600'],
        'cyan'      => ['border' => 'hover:border-cyan-200',      'icon_bg' => 'bg-cyan-100',      'icon_text' => 'text-cyan-600'],
        'teal'      => ['border' => 'hover:border-teal-200',      'icon_bg' => 'bg-teal-100',      'icon_text' => 'text-teal-600'],
        'indigo'    => ['border' => 'hover:border-indigo-200',    'icon_bg' => 'bg-indigo-100',    'icon_text' => 'text-indigo-600'],
        'violet'    => ['border' => 'hover:border-violet-200',    'icon_bg' => 'bg-violet-100',    'icon_text' => 'text-violet-600'],
        'purple'    => ['border' => 'hover:border-purple-200',    'icon_bg' => 'bg-purple-100',    'icon_text' => 'text-purple-600'],
        'rose'      => ['border' => 'hover:border-rose-200',      'icon_bg' => 'bg-rose-100',      'icon_text' => 'text-rose-600'],
        'orange'    => ['border' => 'hover:border-orange-200',    'icon_bg' => 'bg-orange-100',    'icon_text' => 'text-orange-600'],
        'amber'     => ['border' => 'hover:border-amber-200',     'icon_bg' => 'bg-amber-100',     'icon_text' => 'text-amber-600'],
        'yellow'    => ['border' => 'hover:border-yellow-200',    'icon_bg' => 'bg-yellow-100',    'icon_text' => 'text-yellow-600'],
        'emerald'   => ['border' => 'hover:border-emerald-200',   'icon_bg' => 'bg-emerald-100',   'icon_text' => 'text-emerald-600'],
        'pink'      => ['border' => 'hover:border-pink-200',      'icon_bg' => 'bg-pink-100',      'icon_text' => 'text-pink-600'],
        'slate'     => ['border' => 'hover:border-slate-200',     'icon_bg' => 'bg-slate-100',     'icon_text' => 'text-slate-500'],
        'gray'      => ['border' => 'hover:border-gray-200',      'icon_bg' => 'bg-gray-100',      'icon_text' => 'text-gray-500'],
    ];
    $c = $colorMap[$color ?? 'gray'];
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
