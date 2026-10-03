{{--
    Wraps a Livewire file input — <x-file wire:model="…"> or a bare
    <input type="file" wire:model="…"> — and shrinks the phone photos picked in
    it before Livewire uploads them. PDFs and light images go through as they
    are. See resources/js/components/document-upload.js.

    @param int $maxEdge longest side of a shrunk photo, in pixels: 2400 keeps
        the small print of a receipt legible; an illustration needs less
--}}
@props([
    'maxEdge' => 2400,
])

<div x-data="documentUpload({ maxEdge: {{ (int) $maxEdge }} })" x-on:change.capture="intercept($event)" {{ $attributes }}>
    {{ $slot }}

    <p x-show="shrinking" x-cloak class="mt-1 flex items-center gap-1.5 text-xs text-base-content/70">
        <span class="loading loading-spinner loading-xs"></span> {{ __('Preparing the photo…') }}
    </p>
</div>
