@props([
    'icon'    => 'o-inbox',
    'title'   => __('Nothing here yet'),
    'subtitle' => null,
    'action'  => null,   // label du bouton
    'href'    => null,   // lien (optionnel)
    'wireClick' => null, // ou wire:click
])

<div {{ $attributes->class(['flex flex-col items-center justify-center gap-3 py-12 text-center opacity-60']) }}>

    <x-icon :name="$icon" class="h-10 w-10 opacity-40" />

    <div class="space-y-1">
        <p class="text-sm font-semibold">{{ $title }}</p>

        @if($subtitle)
            <p class="text-xs opacity-70">{{ $subtitle }}</p>
        @endif
    </div>

    @if($action)
        <x-button
            :label="$action"
            :href="$href"
            :wire:click="$wireClick"
            class="btn-sm btn-outline mt-2"
        />
    @endif

</div>