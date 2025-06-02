<?php
// ===========================================
// 14. resources/views/components/ui/filter-bar.blade.php
// ===========================================
?>
<x-ui.card class="mb-6">
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            {{ $slot }}
        </div>
    </x-slot>
</x-ui.card>