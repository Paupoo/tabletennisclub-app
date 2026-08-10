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

    {{-- ── Health tiles ─────────────────────────────────────────────────────
    Three figures, read side by side. The worker tile used to show a sentence
    where its neighbours showed a number — and the same sentence the empty state
    repeats 200px below. It now carries the one figure that says whether the
    worker is running: how long the oldest job has been waiting. --}}
    @php
        [$waitIcon, $waitColor, $waitHint] = match ($workerStatus) {
            'stalled' => ['o-exclamation-triangle', 'error', __('Worker probably down')],
            'busy' => ['o-arrow-path', 'primary', __('Jobs are flowing normally')],
            default => ['o-check-circle', 'success', __('Nothing waiting to be processed')],
        };
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-6">
        <x-admin.shared.stat-card
            :label="__('Oldest wait')"
            :value="__(':minutes min', ['minutes' => $oldestPendingMinutes ?? 0])"
            :hint="$waitHint"
            :icon="$waitIcon"
            :color="$waitColor" />

        <x-admin.shared.stat-card
            :label="__('Pending jobs')"
            :value="(string) $pendingCount"
            :hint="__('Waiting to be processed')"
            icon="o-queue-list"
            color="primary" />

        <x-admin.shared.stat-card
            :label="__('Failed jobs')"
            :value="(string) $failedCount"
            :hint="__('Failed recently')"
            icon="o-x-circle"
            :color="$failedCount > 0 ? 'error' : 'neutral'" />
    </div>

    {{-- ── Pending jobs ─────────────────────────────────────────────────────── --}}
    <x-card class="bg-base-100 shadow-sm mt-6" :title="__('Pending jobs')">
        @if (count($pendingJobs) === 0)
            <x-empty-state icon="o-inbox" :heading="__('Queue empty')" :message="__('Nothing waiting to be processed')" />
        @else
            {{-- ── Vue mobile ─────────────────────────────────────────────
            Four columns do not fit a phone, and the table only scrolled
            sideways. Below lg the rows are cards, as thirteen sibling lists
            already do. --}}
            <div class="grid grid-cols-1 gap-3 lg:hidden">
                @foreach ($pendingJobs as $job)
                    <div class="rounded-lg border border-base-300 bg-base-100 p-3" wire:key="mobile-pending-{{ $job['id'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="min-w-0 break-words text-sm font-medium">{{ $job['name'] }}</span>
                            <x-badge :value="$job['queue']" class="badge-ghost badge-sm shrink-0" />
                        </div>
                        <div class="mt-1 flex flex-wrap items-baseline gap-x-2 text-xs text-muted">
                            <span @class(['text-error font-semibold' => $job['is_late']])>{{ $job['age'] }}</span>
                            <span>·</span>
                            <span>{{ __('Attempts') }} : {{ $job['attempts'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="hidden lg:block">
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
            </div>
        @endif
    </x-card>

    {{-- ── Failed jobs ──────────────────────────────────────────────────────── --}}
    <x-card class="bg-base-100 shadow-sm mt-6" :title="__('Failed jobs')">
        @if (count($failedJobs) === 0)
            <x-empty-state icon="o-check-badge" :heading="__('No failures')" :message="__('No job has failed recently.')" />
        @else
            {{-- ── Vue mobile ───────────────────────────────────────────── --}}
            <div class="grid grid-cols-1 gap-3 lg:hidden">
                @foreach ($failedJobs as $job)
                    <div class="rounded-lg border border-base-300 bg-base-100 p-3" wire:key="mobile-failed-{{ $job['uuid'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <span class="min-w-0 break-words text-sm font-medium">{{ $job['name'] }}</span>
                            <x-badge :value="$job['queue']" class="badge-ghost badge-sm shrink-0" />
                        </div>
                        <div class="mt-1 text-xs tabular-nums text-muted">{{ $job['failed_at'] }}</div>
                        <p class="mt-1 break-words font-mono text-xs text-error/80">{{ $job['error'] }}</p>
                        <div class="mt-3 flex items-center gap-1">
                            <x-button
                                icon="o-arrow-path"
                                class="btn-ghost btn-sm"
                                :label="__('Retry')"
                                wire:click="retry('{{ $job['uuid'] }}')"
                                spinner />
                            <x-button
                                icon="o-trash"
                                class="btn-ghost btn-sm text-error"
                                :label="__('Delete')"
                                wire:click="forget('{{ $job['uuid'] }}')"
                                spinner />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="hidden lg:block">
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
            </div>
        @endif
    </x-card>
</div>
