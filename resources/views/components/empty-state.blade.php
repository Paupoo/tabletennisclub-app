@props([
    'icon' => 'o-inbox',
    'heading' => null,
    'message' => null,
    'buttonText' => null,
    'href' => null,
])

<div class="flex flex-col items-center justify-center px-6 py-16 text-center">
    <div class="mb-4 rounded-full bg-base-200 p-4">
        <x-icon :name="$icon" class="h-10 w-10 text-base-content/40" />
    </div>

    @if ($heading)
        <p class="mb-1 text-base font-semibold text-base-content/70">{{ $heading }}</p>
    @endif

    @if ($message)
        <p class="max-w-sm text-sm text-base-content/40">{{ $message }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @elseif ($buttonText && $href)
        <x-button :label="$buttonText" :link="$href" class="btn-primary btn-sm mt-4" />
    @endif
</div>
