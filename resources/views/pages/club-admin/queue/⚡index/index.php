<?php

declare(strict_types=1);

use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use App\Support\QueueHealth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast;

    public function forget(string $uuid): void
    {
        $this->authorizeAccess();

        Artisan::call('queue:forget', ['id' => $uuid]);

        $this->warning(__('Failed job deleted.'));
    }

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function render(): View
    {
        return $this->view([
            'pendingJobs' => $this->pendingJobs(),
            'failedJobs' => $this->failedJobs(),
            'pendingCount' => QueueHealth::pendingCount(),
            'failedCount' => QueueHealth::failedCount(),
            'oldestPendingMinutes' => QueueHealth::oldestPendingMinutes(),
            'workerStatus' => $this->workerStatus(),
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    public function retry(string $uuid): void
    {
        $this->authorizeAccess();

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        $this->success(__('Job queued for retry.'));
    }

    public function retryAll(): void
    {
        $this->authorizeAccess();

        Artisan::call('queue:retry', ['id' => ['all']]);

        $this->success(__('All failed jobs queued for retry.'));
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->can('view-queue-monitoring') ?? false, 403);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Queue monitoring'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function failedJobs(): array
    {
        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(50)
            ->get()
            ->map(fn (object $job): array => [
                'uuid' => $job->uuid,
                'name' => $this->jobName($job->payload),
                'queue' => $job->queue,
                'failed_at' => Carbon::parse($job->failed_at)->format('d/m/Y H:i'),
                'error' => Str::limit(Str::before($job->exception, "\n"), 140),
            ])
            ->all();
    }

    /**
     * Human-readable job class from the serialized payload.
     */
    protected function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);

        return class_basename($decoded['displayName'] ?? __('Unknown'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pendingJobs(): array
    {
        return DB::table('jobs')
            ->orderBy('created_at')
            ->limit(50)
            ->get()
            ->map(fn (object $job): array => [
                'id' => $job->id,
                'name' => $this->jobName($job->payload),
                'queue' => $job->queue,
                'attempts' => $job->attempts,
                'age' => Carbon::createFromTimestamp((int) $job->created_at)->diffForHumans(),
                'is_late' => Carbon::createFromTimestamp((int) $job->created_at)->diffInMinutes(now()) > QueueHealth::STALLED_AFTER_MINUTES,
            ])
            ->all();
    }

    /**
     * Worker state deduced from the queue backlog: 'stalled' when the oldest
     * pending job exceeds the threshold, 'busy' when jobs flow, 'idle' when
     * the queue is empty (nothing to prove the worker up or down).
     */
    protected function workerStatus(): string
    {
        if (QueueHealth::isStalled()) {
            return 'stalled';
        }

        return QueueHealth::pendingCount() > 0 ? 'busy' : 'idle';
    }
};
