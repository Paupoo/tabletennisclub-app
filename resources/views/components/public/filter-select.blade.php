<div class="relative">
    <select {{ $attributes->merge(['class' => 'appearance-none bg-base-100 pl-3 pr-8 py-1.5 text-sm font-medium text-muted border border-base-300 rounded-lg shadow-xs cursor-pointer hover:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all']) }}>
        {{ $slot }}
    </select>
    <div class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-subtle">
        <x-icon name="o-chevron-down" class="h-3.5 w-3.5" />
    </div>
</div>
