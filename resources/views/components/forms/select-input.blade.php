<?php
// ===========================================
// resources/views/components/form/select-input.blade.php
// ===========================================
?>
<select {{ $attributes->merge(['class' => 'block w-full sm:w-auto pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-hidden focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md']) }}>
    {{ $slot }}
</select>