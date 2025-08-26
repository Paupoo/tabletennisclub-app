<?php
// ===========================================
// 18. resources/views/components/tournament/status-badge.blade.php
// ===========================================
?>
@props([
    'status' => '',
    ])


@php
$statusMap = [
    \App\Enums\TournamentStatusEnum::DRAFT->value => ['variant' => 'draft', 'label' => __('Draft')],
    \App\Enums\TournamentStatusEnum::PUBLISHED->value => ['variant' => 'open', 'label' => __('Published')],
    \App\Enums\TournamentStatusEnum::SETUP->value=> ['variant' => 'pending', 'label' => __('Locked')],
    \App\Enums\TournamentStatusEnum::PENDING->value => ['variant' => 'closed', 'label' => __('Pending')],
    \App\Enums\TournamentStatusEnum::CLOSED->value => ['variant' => 'closed', 'label' => __('closed')],
    \App\Enums\TournamentStatusEnum::CANCELLED->value => ['variant' => 'closed', 'label' => __('Cancelled')],
];

$config = $statusMap[$status->value] ?? ['variant' => 'default', 'label' => ucfirst($status->value)];
@endphp

<x-ui.badge {{ $attributes->merge(['class' => 'ml-2']) }} variant="{{ $config['variant'] }}">
    {{ $config['label'] }}
</x-ui.badge>