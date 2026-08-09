@props([
    // Livewire property the cropped JPEG is uploaded to (via $wire.upload).
    'model' => 'photo',
    // Fully-resolved preview URL (temporary upload, stored photo or placeholder).
    'preview' => null,
    // Blade markup for an optional delete button (shown next to the trigger).
    'delete' => null,
    'label' => null,
])

<div x-data="avatarCropper({ model: '{{ $model }}' })" x-ref="root"
    data-invalid-message="{{ __('Please choose an image file.') }}"
    data-failed-message="{{ __('The photo could not be processed. Please try again.') }}">
    @if ($label)
        <span class="fieldset-legend mb-0.5 block">{{ $label }}</span>
    @endif

    <input type="file" x-ref="input" accept="image/*" class="hidden" @change="selected($event)">

    <div class="flex items-center gap-4">
        <img x-ref="preview" src="{{ $preview ?? asset('images/empty-user.jpg') }}"
            alt="{{ __('Avatar') }}" class="h-24 w-24 rounded-full object-cover ring ring-base-200">

        <div class="flex flex-col gap-2">
            <x-button :label="__('Choose a photo')" icon="o-camera" class="btn-outline btn-sm"
                @click="pick()" ::disabled="processing" ::class="processing && 'opacity-60'" />
            {{ $delete }}
        </div>
    </div>

    <p x-show="error" x-text="error" x-cloak class="text-error text-sm mt-2"></p>

    {{-- Full-screen crop modal (mobile-first). Kept out of Livewire's DOM morph. --}}
    <div wire:ignore>
        <template x-teleport="body">
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex flex-col bg-black/90 backdrop-blur-sm">
                <div class="flex-1 min-h-0 overflow-hidden p-4">
                    <img x-ref="image" alt="" class="block max-w-full">
                </div>
                <div class="flex items-center justify-center gap-4 bg-base-100 p-4">
                    <x-button icon="o-magnifying-glass-minus" class="btn-circle btn-ghost" @click="zoom(-0.1)" />
                    <x-button icon="o-magnifying-glass-plus" class="btn-circle btn-ghost" @click="zoom(0.1)" />
                    <div class="flex-1"></div>
                    <x-button :label="__('Cancel')" class="btn-ghost" @click="cancel()" ::disabled="processing" />
                    <x-button :label="__('Use this photo')" icon="o-check" class="btn-primary" @click="confirm()"
                        ::disabled="processing" ::class="processing && 'loading'" />
                </div>
            </div>
        </template>
    </div>
</div>
