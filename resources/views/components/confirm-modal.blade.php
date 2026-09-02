@props([
    'model',
    'title',
    'subtitle' => null,
    'confirmLabel' => 'Confirm',
    'confirmClass' => 'btn-error',
    'confirmAction',
    'cancelAction' => null,
    'open' => null,
])

@php $cancelClick = $cancelAction ?? '$set(\'' . $model . '\', false)'; @endphp

<x-app-modal :wire:model="$model" :open="$open" :title="$title" :subtitle="$subtitle" class="backdrop-blur">
    {{ $slot }}
    <x-slot:actions>
        <x-button :label="__('Cancel')" wire:click="{{ $cancelClick }}" />
        <x-button :label="__($confirmLabel)" :class="$confirmClass" spinner wire:click="{{ $confirmAction }}" />
    </x-slot:actions>
</x-app-modal>
