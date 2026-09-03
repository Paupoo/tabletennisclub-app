@props([
    // Livewire property the downscaled JPEG is uploaded to (via $wire.upload).
    'model' => 'image',
    // Livewire properties holding the focal point, in percentages.
    'focalXProperty' => 'imageFocalX',
    'focalYProperty' => 'imageFocalY',
    'focalX' => 50,
    'focalY' => 50,
    // Fully-resolved preview URL, or null when the article has no image yet.
    'preview' => null,
    // Blade markup for an optional delete button.
    'delete' => null,
    'label' => null,
])

<div x-ref="root"
    x-data="imageFocalPicker({
        model: '{{ $model }}',
        focalXProperty: '{{ $focalXProperty }}',
        focalYProperty: '{{ $focalYProperty }}',
        x: {{ (int) $focalX }},
        y: {{ (int) $focalY }},
    })"
    data-invalid-message="{{ __('Please choose an image file.') }}"
    data-failed-message="{{ __('The image could not be processed. Please try again.') }}">

    @if ($label)
        <span class="fieldset-legend mb-0.5 block">{{ $label }}</span>
    @endif

    <input type="file" x-ref="input" accept="image/*" class="hidden" @change="selected($event)">

    @if ($preview)
        {{-- The point is placed on the whole photo, so the box must hug the image:
             a letterboxed container would map clicks to pixels that aren't there. --}}
        <div class="flex justify-center">
            <div x-ref="canvas" tabindex="0" role="application"
                :aria-label="`{{ __('Focal point of the image') }} — ${x}% / ${y}%`"
                class="relative inline-block cursor-crosshair touch-none rounded-lg border border-base-300 focus:border-club-blue focus:outline-none focus:ring-4 focus:ring-club-blue/10"
                @pointerdown.prevent="start($event)"
                @pointermove="drag($event)"
                @pointerup="stop()"
                @pointercancel="stop()"
                @keydown.arrow-left.prevent="nudge(-2, 0)"
                @keydown.arrow-right.prevent="nudge(2, 0)"
                @keydown.arrow-up.prevent="nudge(0, -2)"
                @keydown.arrow-down.prevent="nudge(0, 2)">

                <img :src="previewUrl ?? '{{ $preview }}'" alt="{{ __('Current image') }}"
                    class="block max-h-44 w-auto rounded-lg" />

                {{-- The reticle. --}}
                <div class="pointer-events-none absolute h-6 w-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow ring-2 ring-club-blue"
                    :style="`left: ${x}%; top: ${y}%`">
                    <span class="absolute left-1/2 top-1/2 h-1.5 w-1.5 -translate-x-1/2 -translate-y-1/2 rounded-full bg-club-blue"></span>
                </div>
            </div>
        </div>

        <p class="mt-2 text-xs text-gray-500">
            {{ __('Click the photo where the subject is. Arrow keys nudge the point.') }}
        </p>

        {{-- What the two most punishing surfaces will actually show. --}}
        <div class="mt-3 grid grid-cols-2 gap-3">
            <figure>
                <div class="aspect-[2.83] overflow-hidden rounded border border-base-300 bg-gray-100">
                    <img :src="previewUrl ?? '{{ $preview }}'" alt=""
                        class="h-full w-full object-cover" :style="`object-position: ${position}`" />
                </div>
                <figcaption class="mt-1 flex items-center gap-1 text-[11px] text-gray-400">
                    <x-icon name="o-computer-desktop" class="h-3 w-3" />{{ __('Desktop') }}
                </figcaption>
            </figure>
            <figure>
                <div class="aspect-[1.24] overflow-hidden rounded border border-base-300 bg-gray-100">
                    <img :src="previewUrl ?? '{{ $preview }}'" alt=""
                        class="h-full w-full object-cover" :style="`object-position: ${position}`" />
                </div>
                <figcaption class="mt-1 flex items-center gap-1 text-[11px] text-gray-400">
                    <x-icon name="o-device-phone-mobile" class="h-3 w-3" />{{ __('Mobile') }}
                </figcaption>
            </figure>
        </div>
    @endif

    <div class="mt-3 flex flex-col gap-2">
        <x-button :label="$preview ? __('Replace the image') : __('Choose an image')"
            icon="o-photo" class="btn-outline btn-sm"
            @click="pick()" ::disabled="processing" ::class="processing && 'opacity-60'" />
        {{ $delete }}
    </div>

    <p class="mt-1 text-xs text-gray-400">{{ __('JPG, PNG, WebP — automatically optimised on upload.') }}</p>

    <p x-show="error" x-text="error" x-cloak class="mt-2 text-sm text-error"></p>
</div>
