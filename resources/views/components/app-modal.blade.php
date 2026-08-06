@props([
    'title',
    'subtitle' => null,
    'open' => null,
])

{{--
    Mary's <x-modal> renders <dialog class="modal"> with no accessible name, so a
    screen reader announces "dialog" and stops there. Its render passes unknown
    attributes straight through to the <dialog>, which is how aria-label lands on
    the right element without patching the package.

    The title is required: a modal takes the whole screen away from whoever opens
    it, and an anonymous one leaves them with no way to tell what they are about
    to confirm.

    `:open` takes the same property as wire:model and holds back the body while
    the modal is shut. A closed modal used to ship its whole content on every
    render — 27 kB of member list in one <select> nobody had asked to see. The
    dialog shell stays, so Alpine keeps the open/close mechanics it entangles
    with; only the contents wait. Omitting `:open` renders as before.
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
    @if ($open === null || $open)
        {{ $slot }}

        @isset($actions)
            <x-slot:actions>{{ $actions }}</x-slot:actions>
        @endisset
    @endif
</x-modal>
