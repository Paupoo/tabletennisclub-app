@php
    $colorMap = [
        'blue'   => ['border' => 'hover:border-blue-300',   'bg' => 'group-hover:bg-blue-50 dark:group-hover:bg-blue-900/20',   'icon_bg' => 'bg-blue-100 dark:bg-blue-900/30',   'icon_text' => 'text-blue-600'],
        'cyan'   => ['border' => 'hover:border-cyan-300',   'bg' => 'group-hover:bg-cyan-50 dark:group-hover:bg-cyan-900/20',   'icon_bg' => 'bg-cyan-100 dark:bg-cyan-900/30',   'icon_text' => 'text-cyan-600'],
        'teal'   => ['border' => 'hover:border-teal-300',   'bg' => 'group-hover:bg-teal-50 dark:group-hover:bg-teal-900/20',   'icon_bg' => 'bg-teal-100 dark:bg-teal-900/30',   'icon_text' => 'text-teal-600'],
        'indigo' => ['border' => 'hover:border-indigo-300', 'bg' => 'group-hover:bg-indigo-50 dark:group-hover:bg-indigo-900/20','icon_bg' => 'bg-indigo-100 dark:bg-indigo-900/30','icon_text' => 'text-indigo-600'],
        'violet' => ['border' => 'hover:border-violet-300', 'bg' => 'group-hover:bg-violet-50 dark:group-hover:bg-violet-900/20','icon_bg' => 'bg-violet-100 dark:bg-violet-900/30','icon_text' => 'text-violet-600'],
        'purple' => ['border' => 'hover:border-purple-300', 'bg' => 'group-hover:bg-purple-50 dark:group-hover:bg-purple-900/20','icon_bg' => 'bg-purple-100 dark:bg-purple-900/30','icon_text' => 'text-purple-600'],
        'rose'   => ['border' => 'hover:border-rose-300',   'bg' => 'group-hover:bg-rose-50 dark:group-hover:bg-rose-900/20',   'icon_bg' => 'bg-rose-100 dark:bg-rose-900/30',   'icon_text' => 'text-rose-600'],
        'orange' => ['border' => 'hover:border-orange-300', 'bg' => 'group-hover:bg-orange-50 dark:group-hover:bg-orange-900/20','icon_bg' => 'bg-orange-100 dark:bg-orange-900/30','icon_text' => 'text-orange-600'],
        'amber'  => ['border' => 'hover:border-amber-300',  'bg' => 'group-hover:bg-amber-50 dark:group-hover:bg-amber-900/20',  'icon_bg' => 'bg-amber-100 dark:bg-amber-900/30',  'icon_text' => 'text-amber-600'],
        'yellow' => ['border' => 'hover:border-yellow-300', 'bg' => 'group-hover:bg-yellow-50 dark:group-hover:bg-yellow-900/20','icon_bg' => 'bg-yellow-100 dark:bg-yellow-900/30','icon_text' => 'text-yellow-600'],
        'emerald'=> ['border' => 'hover:border-emerald-300','bg' => 'group-hover:bg-emerald-50 dark:group-hover:bg-emerald-900/20','icon_bg'=> 'bg-emerald-100 dark:bg-emerald-900/30','icon_text'=> 'text-emerald-600'],
        'pink'   => ['border' => 'hover:border-pink-300',   'bg' => 'group-hover:bg-pink-50 dark:group-hover:bg-pink-900/20',   'icon_bg' => 'bg-pink-100 dark:bg-pink-900/30',   'icon_text' => 'text-pink-600'],
        'slate'  => ['border' => 'hover:border-slate-300',  'bg' => 'group-hover:bg-slate-50 dark:group-hover:bg-slate-900/20', 'icon_bg' => 'bg-slate-100 dark:bg-slate-900/30', 'icon_text' => 'text-slate-600'],
        'gray'   => ['border' => 'hover:border-base-400',   'bg' => 'group-hover:bg-base-200',                                  'icon_bg' => 'bg-base-200',                         'icon_text' => 'text-base-content/60'],
    ];
    $c = $colorMap[$color ?? 'gray'];
@endphp
<a href="#"
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
