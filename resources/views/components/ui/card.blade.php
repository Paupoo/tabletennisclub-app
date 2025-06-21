<?php
// ===========================================
// 13. resources/views/components/ui/card.blade.php
// ===========================================
?>
<div {{ $attributes->merge(['class' => 'bg-white shadow-sm rounded-lg']) }}>
    @if($header ?? false)
        <div class="px-6 py-4 border-b border-gray-200">
            {{ $header }}
        </div>
    @endif
    
    <div class="px-6 py-4">
        {{ $slot }}
    </div>
</div>