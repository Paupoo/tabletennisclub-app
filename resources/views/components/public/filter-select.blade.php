<div class="relative">
    <select {{ $attributes->merge(['class' => 'appearance-none bg-white pl-3 pr-8 py-1.5 text-sm font-medium text-gray-700 border border-gray-200 rounded-lg shadow-xs cursor-pointer hover:border-club-blue/50 focus:outline-none focus:ring-2 focus:ring-club-blue/20 focus:border-club-blue transition-all']) }}>
        {{ $slot }}
    </select>
    <div class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-gray-400">
        <x-icon name="o-chevron-down" class="h-3.5 w-3.5" />
    </div>
</div>
