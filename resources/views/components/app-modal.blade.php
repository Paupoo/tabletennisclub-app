@props([
    'title',
    'subtitle' => null,
])

{{--
    Mary's <x-modal> renders <dialog class="modal"> with no accessible name, so a
    screen reader announces "dialog" and stops there. Its render passes unknown
    attributes straight through to the <dialog>, which is how aria-label lands on
    the right element without patching the package.

    The title is required: a modal takes the whole screen away from whoever opens
    it, and an anonymous one leaves them with no way to tell what they are about
    to confirm.
--}}
@php
    if (blank($title)) {
        throw new InvalidArgumentException('<x-app-modal> requires a title: it becomes the modal\'s accessible name.');
    }
@endphp

<x-modal
    :title="$title"
    :subtitle="$subtitle"
    role="dialog"
    aria-modal="true"
    :aria-label="$title"
    {{ $attributes }}
>
    {{ $slot }}

    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset
</x-modal>
