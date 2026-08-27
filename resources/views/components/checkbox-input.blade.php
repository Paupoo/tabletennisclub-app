@props([
    'disabled' => false,
    'checked' => false,
])

<input type="checkbox" {{ $disabled ? 'disabled' : '' }} {{ $checked ? 'checked' : '' }} {!! $attributes->merge([
    'class' =>
        'disabled:opacity-50 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-primary focus:ring-primary rounded-md shadow-xs',
]) !!}>
