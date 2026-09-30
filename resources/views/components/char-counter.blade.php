{{--
    Live character count under a text field whose validation sets a `max:`.
    It reads the property through `$wire`, which `wire:model` updates on every
    keystroke without a request, so it follows the field even when the model is
    deferred. Pair it with a `maxlength` on the field, set to the same `max`.

    @param string $model the Livewire property bound by the field's wire:model
    @param int $max the `max:` of the validation rule
--}}
@props(['model', 'max'])

<div x-data {{ $attributes->class('-mt-1 text-right text-xs tabular-nums text-subtle') }}
    x-bind:class="{ '!text-error': ($wire.{{ $model }} ?? '').length >= {{ (int) $max }} }">
    <span x-text="($wire.{{ $model }} ?? '').length">0</span> / {{ (int) $max }}
</div>
