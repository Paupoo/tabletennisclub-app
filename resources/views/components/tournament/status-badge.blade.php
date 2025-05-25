<?php
// ===========================================
// 18. resources/views/components/tournament/status-badge.blade.php
// ===========================================
?>
@props(['status'])

@php
$statusMap = [
    'draft' => ['variant' => 'draft', 'label' => 'Brouillon'],
    'open' => ['variant' => 'open', 'label' => 'Ouvert'],
    'pending' => ['variant' => 'pending', 'label' => 'En cours'],
    'closed' => ['variant' => 'closed', 'label' => 'Fermé'],
];

$config = $statusMap[$status] ?? ['variant' => 'default', 'label' => ucfirst($status)];
@endphp

<x-ui.badge variant="{{ $config['variant'] }}">
    {{ $config['label'] }}
</x-ui.badge>