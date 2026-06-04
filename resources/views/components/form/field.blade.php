@props(['name', 'label'])

<x-input-label :for="$name" :value="$label" />
{{ $slot }}
<x-input-error class="mt-2" :messages="$errors->get($name)" />
