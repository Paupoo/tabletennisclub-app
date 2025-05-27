<?php
// ===========================================
// 9. resources/views/components/table/header-cell.blade.php
// ===========================================
?>
<th {{ $attributes->merge(['class' => 'px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider']) }}>
    {{ $slot }}
</th>