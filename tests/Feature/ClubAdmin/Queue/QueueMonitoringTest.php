<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\Role;
use App\Mail\QueueStalledMail;
use App\Support\QueueHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

const QUEUE_COMPONENT = 'pages::club-admin.queue.index';

function insertPendingJob(int $minutesAgo = 0, string $displayName = 'App\\Jobs\\FakePendingJob'): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => json_encode(['uuid' => (string) Str::uuid(), 'displayName' => $displayName]),
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes($minutesAgo)->getTimestamp(),
        'created_at' => now()->subMinutes($minutesAgo)->getTimestamp(),
    ]);
}

function insertFailedJob(string $displayName = 'App\\Jobs\\FakeFailedJob'): string
{
    $uuid = (string) Str::uuid();

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'uuid' => $uuid,
            'displayName' => $displayName,
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['commandName' => $displayName, 'command' => 'O:8:"stdClass":0:{}'],
        ]),
        'exception' => "RuntimeException: SMTP connection refused\n#0 stack trace...",
        'failed_at' => now(),
    ]);

    return $uuid;
}

// ── Access control ──────────────────────────────────────────────────────────

test('a regular member cannot open the queue monitoring', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('admin.queue.index'))
        ->assertForbidden();
});

test('a committee member can open the queue monitoring', function (): void {
    $this->actingAs(tap($this->createFakeCommitteeMember(), fn ($u) => $u->assignRole(Role::SUPERVISION->value)))
        ->get(route('admin.queue.index'))
        ->assertSuccessful();
});

test('an admin can open the queue monitoring', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.queue.index'))
        ->assertSuccessful();
});

// ── Page content ────────────────────────────────────────────────────────────

test('pending jobs are listed with their name', function (): void {
    insertPendingJob(minutesAgo: 2, displayName: 'App\\Jobs\\SendWeeklyReport');

    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.queue.index'))
        ->assertSee('SendWeeklyReport');
});

test('failed jobs are listed with the first line of the exception', function (): void {
    insertFailedJob(displayName: 'App\\Mail\\SomeBrokenMail');

    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.queue.index'))
        ->assertSee('SomeBrokenMail')
        ->assertSee('SMTP connection refused');
});

test('the worker is reported down when the oldest pending job is too old', function (): void {
    insertPendingJob(minutesAgo: QueueHealth::STALLED_AFTER_MINUTES + 5);

    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.queue.index'))
        ->assertSee(__('Worker probably down'));
});

// ── Actions ─────────────────────────────────────────────────────────────────

test('retrying a failed job pushes it back onto the queue', function (): void {
    $uuid = insertFailedJob();

    Livewire::actingAs($this->createFakeAdmin())
        ->test(QUEUE_COMPONENT)
        ->call('retry', $uuid);

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(1);
});

test('retry all pushes every failed job back onto the queue', function (): void {
    insertFailedJob();
    insertFailedJob();

    Livewire::actingAs($this->createFakeAdmin())
        ->test(QUEUE_COMPONENT)
        ->call('retryAll');

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(2);
});

test('a failed job can be deleted', function (): void {
    $uuid = insertFailedJob();

    Livewire::actingAs($this->createFakeAdmin())
        ->test(QUEUE_COMPONENT)
        ->call('forget', $uuid);

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

// ── Scheduled alert command ─────────────────────────────────────────────────

test('the health check emails the admins when a pending job is stale', function (): void {
    Mail::fake();

    $admin = $this->createFakeAdmin();
    insertPendingJob(minutesAgo: QueueHealth::STALLED_AFTER_MINUTES + 20);

    $this->artisan('queue:check-health')->assertSuccessful();

    Mail::assertSent(QueueStalledMail::class, fn (QueueStalledMail $mail): bool => $mail->hasTo($admin->email));
});

test('the health check stays silent when the queue is healthy', function (): void {
    Mail::fake();

    $this->createFakeAdmin();
    insertPendingJob(minutesAgo: 1);

    $this->artisan('queue:check-health')->assertSuccessful();

    Mail::assertNothingSent();
});

test('the stalled alert mail renders the figures and the monitoring link', function (): void {
    $mail = new QueueStalledMail(pendingCount: 4, oldestMinutes: 42, failedCount: 2);
    $html = $mail->render();

    expect($html)
        ->toContain('42')
        ->toContain(e(route('admin.queue.index')));

    expect($mail->envelope()->subject)
        ->toBe(__('[:app] Queue alert — the worker looks down', ['app' => config('app.name')]));
});

// ── Dashboard badge ─────────────────────────────────────────────────────────

test('the dashboard alerts the admin when jobs have failed', function (): void {
    insertFailedJob();

    $this->actingAs($this->createFakeAdmin())
        ->get(route('dashboard'))
        ->assertSee("tâche en échec dans la file d'attente");
});

test('the dashboard alerts the admin when the worker looks down', function (): void {
    insertPendingJob(minutesAgo: QueueHealth::STALLED_AFTER_MINUTES + 5);

    $this->actingAs($this->createFakeAdmin())
        ->get(route('dashboard'))
        ->assertSee("File d'attente bloquée");
});

test('the dashboard shows no queue alert to a regular member', function (): void {
    insertFailedJob();

    $this->actingAs($this->createFakeUser())
        ->get(route('dashboard'))
        ->assertDontSee("tâche en échec dans la file d'attente");
});
