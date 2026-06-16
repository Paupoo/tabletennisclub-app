@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-club-blue dark:focus:border-club-blue-light focus:ring-0 rounded-md shadow-xs']) !!}>
