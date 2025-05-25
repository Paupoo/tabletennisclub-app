<?php
// ===========================================
// 16. resources/views/components/ui/legend-item.blade.php
// ===========================================
?>
<div class="flex items-center space-x-3">
    <x-ui.icon name="{{ $icon }}" class="w-5 h-5 {{ $iconClass ?? 'text-gray-400' }}" />
    <span class="text-sm text-gray-600">{{ $slot }}</span>
</div>