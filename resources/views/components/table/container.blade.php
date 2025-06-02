<?php
// ===========================================
// 7. resources/views/components/table/container.blade.php
// ===========================================
?>
<div class="bg-white shadow overflow-hidden sm:rounded-lg">
    <table class="min-w-full divide-y divide-gray-200">
        {{ $slot }}
    </table>
    
    @if($pagination ?? false)
        <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
            {{ $pagination }}
        </div>
    @endif
</div>