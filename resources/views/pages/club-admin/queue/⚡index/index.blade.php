<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div wire:poll.30s>
    <x-header :title="__('Queue monitoring')" :subtitle="__('Pending jobs, failures and worker health')" separator progress-indicator>
        <x-slot:actions>
            @if ($failedCount > 0)
                <x-button
                    :label="__('Retry all')"
                    icon="o-arrow-path"
                    class="btn-primary btn-sm"
                    wire:click="retryAll"
                    spinner="retryAll"
                />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Health tiles ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
        {{-- Worker status --}}
        <x-card class="bg-base-100 shadow-sm">
            <div class="flex items-center gap-3">
                @if ($workerStatus === 'stalled')
                    <div class="w-10 h-10 rounded-full bg-error/10 flex items-center justify-center shrink-0">
                        <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-error" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-error">{{ __('Worker probably down') }}</p>
                        <p class="text-xs text-base-content/50">{{ __('Oldest job waiting for :minutes min', ['minutes' => $oldestPendingMinutes]) }}</p>
                    </div>
                @elseif ($workerStatus === 'busy')
                    <div class="w-10 h-10 rounded-full bg-info/10 flex items-center justify-center shrink-0">
                        <x-icon name="o-arrow-path" class="w-5 h-5 text-info" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-info">{{ __('Processing') }}</p>
                        <p class="text-xs text-base-content/50">{{ __('Jobs are flowing normally') }}</p>
                    </div>
                @else
                    <div class="w-10 h-10 rounded-full bg-success/10 flex items-center justify-center shrink-0">
                        <x-icon name="o-check-circle" class="w-5 h-5 text-success" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-success">{{ __('Queue empty') }}</p>
                        <p class="text-xs text-base-content/50">{{ __('Nothing waiting to be processed') }}</p>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- Pending count --}}
        <x-card class="bg-base-100 shadow-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                    <x-icon name="o-queue-list" class="w-5 h-5 text-primary" />
                </div>
                <div>
                    <p class="text-2xl font-bold tabular-nums leading-none">{{ $pendingCount }}</p>
                    <p class="text-xs text-base-content/50 mt-1">{{ __('Pending jobs') }}</p>
                </div>
            </div>
        </x-card>

        {{-- Failed count --}}
        <x-card class="bg-base-100 shadow-sm">
            <div class="flex items-center gap-3">
                <div @class([
                    'w-10 h-10 rounded-full flex items-center justify-center shrink-0',
                    'bg-error/10' => $failedCount > 0,
                    'bg-base-200' => $failedCount === 0,
                ])>
                    <x-icon name="o-x-circle" @class(['w-5 h-5', 'text-error' => $failedCount > 0, 'text-base-content/30' => $failedCount === 0]) />
                </div>
                <div>
                    <p class="text-2xl font-bold tabular-nums leading-none">{{ $failedCount }}</p>
                    <p class="text-xs text-base-content/50 mt-1">{{ __('Failed jobs') }}</p>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ── Pending jobs ─────────────────────────────────────────────────────── --}}
    <x-card class="bg-base-100 shadow-sm mt-6" :title="__('Pending jobs')">
        @if (count($pendingJobs) === 0)
            <x-empty-state icon="o-inbox" :heading="__('Queue empty')" :message="__('Nothing waiting to be processed')" />
        @else
            <x-table :headers="[
                ['key' => 'name',     'label' => __('Job')],
                ['key' => 'queue',    'label' => __('Queue')],
                ['key' => 'attempts', 'label' => __('Attempts')],
                ['key' => 'age',      'label' => __('Waiting since')],
            ]" :rows="$pendingJobs">
                @scope('cell_name', $job)
                <span class="text-sm font-medium">{{ $job['name'] }}</span>
                @endscope

                @scope('cell_queue', $job)
                <x-badge :value="$job['queue']" class="badge-ghost badge-sm" />
                @endscope

                @scope('cell_age', $job)
                <span @class(['text-sm whitespace-nowrap', 'text-error font-semibold' => $job['is_late']])>
                    {{ $job['age'] }}
                    @if ($job['is_late'])
                        <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 inline-block ml-1" />
                    @endif
                </span>
                @endscope
            </x-table>
        @endif
    </x-card>

    {{-- ── Failed jobs ──────────────────────────────────────────────────────── --}}
    <x-card class="bg-base-100 shadow-sm mt-6" :title="__('Failed jobs')">
        @if (count($failedJobs) === 0)
            <x-empty-state icon="o-check-badge" :heading="__('No failures')" :message="__('No job has failed recently.')" />
        @else
            <x-table :headers="[
                ['key' => 'name',      'label' => __('Job')],
                ['key' => 'queue',     'label' => __('Queue')],
                ['key' => 'failed_at', 'label' => __('Failed at')],
                ['key' => 'error',     'label' => __('Error')],
                ['key' => 'actions',   'label' => ''],
            ]" :rows="$failedJobs">
                @scope('cell_name', $job)
                <span class="text-sm font-medium">{{ $job['name'] }}</span>
                @endscope

                @scope('cell_queue', $job)
                <x-badge :value="$job['queue']" class="badge-ghost badge-sm" />
                @endscope

                @scope('cell_failed_at', $job)
                <span class="text-sm tabular-nums whitespace-nowrap">{{ $job['failed_at'] }}</span>
                @endscope

                @scope('cell_error', $job)
                <span class="font-mono text-xs text-error/80">{{ $job['error'] }}</span>
                @endscope

                @scope('cell_actions', $job)
                <div class="flex items-center gap-1 justify-end">
                    <x-button
                        icon="o-arrow-path"
                        class="btn-ghost btn-sm"
                        :tooltip="__('Retry')"
                        wire:click="retry('{{ $job['uuid'] }}')"
                        spinner :aria-label="__('Retry')" />
                    <x-button
                        icon="o-trash"
                        class="btn-ghost btn-sm text-error"
                        :tooltip="__('Delete')"
                        wire:click="forget('{{ $job['uuid'] }}')"
                        spinner :aria-label="__('Delete')" />
                </div>
                @endscope
            </x-table>
        @endif
    </x-card>
</div>
