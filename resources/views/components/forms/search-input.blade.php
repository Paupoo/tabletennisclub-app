<?php
// ===========================================
// resources/views/components/form/search-input.blade.php
// ===========================================
?>
<div class="relative">
    <input 
        type="text" 
        placeholder="{{ $placeholder ?? 'Rechercher...' }}"
        {{ $attributes->merge(['class' => 'block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-hidden focus:placeholder-gray-400 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm']) }}
    >
    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
        <x-icon name="o-magnifying-glass" class="h-5 w-5 text-gray-400" />
    </div>
</div>