@props(['value'])

<label {{ $attributes->merge(['class' => 'px-2 py-1 text-center rounded-full bg-gray-300 w-fit uppercase text-white font-normal text-xs']) }}>
    {{ $value ?? $slot }}
</label>