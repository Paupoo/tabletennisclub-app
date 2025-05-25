<?php
// ===========================================
// 11. resources/views/components/table/row.blade.php
// ===========================================
?>
<tr {{ $attributes->merge(['class' => 'hover:bg-gray-50']) }}>
    {{ $slot }}
</tr>