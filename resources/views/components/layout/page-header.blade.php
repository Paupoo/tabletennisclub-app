<?php
// ===========================================
// resources/views/components/layout/page-header.blade.php
// ===========================================
?>
<div class="mb-8">
    <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>
    @if($description ?? false)
        <p class="mt-2 text-sm text-gray-600">{{ $description }}</p>
    @endif
</div>