@props([
    'href' => '#',
    'icon' => 'default',
    'textColor' => 'text-gray-700',
    'textHover' => 'text-gray-900',
    'text'
])

<a href="{{ $href }}"
    class="flex items-center px-4 py-2 text-sm {{ $textColor }} hover:bg-gray-100 hover:{{ $textHover }} }}">
    <x-ui.icon name="{{ $icon }}" class="mr-2" />
    {{ $text }}
</a>
