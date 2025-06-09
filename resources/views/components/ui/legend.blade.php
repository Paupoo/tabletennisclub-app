<?php
// ===========================================
// 15. resources/views/components/ui/legend.blade.php
// ===========================================
?>
<x-ui.card class="mt-6">
    <h3 class="text-lg font-medium text-gray-900 mb-4">{{ $title ?? __('Actions legend') }}</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{ $slot }}
    </div>
</x-ui.card>