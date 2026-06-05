@props([
    'model',
    'title',
    'subtitle' => null,
    'confirmLabel' => 'Confirm',
    'confirmClass' => 'btn-error',
    'confirmAction',
])

<x-modal :wire:model="$model" :title="$title" :subtitle="$subtitle" class="backdrop-blur">
    {{ $slot }}
    <x-slot:actions>
        <x-button :label="__('Cancel')" @click="$wire.{{ $model }} = false" />
        <x-button :label="__($confirmLabel)" :class="$confirmClass" spinner wire:click="{{ $confirmAction }}" />
    </x-slot:actions>
</x-modal>
