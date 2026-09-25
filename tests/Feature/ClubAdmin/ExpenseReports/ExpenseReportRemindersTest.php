<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\SubmitExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsDigestNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportSubmittedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
    Carbon::setTestNow('2026-09-27 19:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('the bell on submission', function (): void {
    it('rings for every decider but the author, and sends no mail', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        $backup = User::factory()->withRole(Role::EXPENSE_REPORTS)->create();
        $author = User::factory()->withRole(Role::TREASURY)->create();
        $committee = User::factory()->isCommitteeMember()->create();

        (new SubmitExpenseReport)(
            author: $author,
            category: ExpenseCategory::Other,
            description: 'Scotch',
            amount: 6.8,
            spentOn: Carbon::parse('2026-09-12'),
            refundIban: 'BE68539007547034',
            files: [UploadedFile::fake()->image('ticket.jpg')],
        );

        Notification::assertSentTo([$treasurer, $backup], ExpenseReportSubmittedNotification::class,
            fn (ExpenseReportSubmittedNotification $n, array $channels): bool => $channels === ['database']);
        Notification::assertNotSentTo([$author, $committee], ExpenseReportSubmittedNotification::class);
    });
});

describe('the sunday digest', function (): void {
    it('lists every report still waiting, oldest first, to each decider but for their own', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create(['first_name' => 'Trésorier']);
        $backup = User::factory()->withRole(Role::EXPENSE_REPORTS)->create();
        $old = ExpenseReport::factory()->create(['description' => 'Ancienne note', 'created_at' => now()->subDays(12)]);
        $fresh = ExpenseReport::factory()->create(['description' => 'Note de la semaine', 'created_at' => now()->subDays(2)]);
        $own = ExpenseReport::factory()->for($treasurer)->create(['description' => 'Note du trésorier']);
        ExpenseReport::factory()->accepted()->create(['description' => 'Déjà acceptée']);

        $this->artisan('expense-reports:send-digest')->assertSuccessful();

        Notification::assertSentTo($treasurer, ExpenseReportsDigestNotification::class,
            fn (ExpenseReportsDigestNotification $n): bool => $n->reports->modelKeys() === [$old->id, $fresh->id]);
        Notification::assertSentTo($backup, ExpenseReportsDigestNotification::class,
            fn (ExpenseReportsDigestNotification $n): bool => $n->reports->modelKeys() === [$old->id, $fresh->id, $own->id]);

        $mail = new ExpenseReportsDigestNotification(ExpenseReport::with('user')->whereKey([$old->id, $fresh->id])->orderBy('id')->get())
            ->toMail($treasurer)->render();

        expect((string) $mail)->toContain('Ancienne note')
            ->toContain(__('waiting for :days days', ['days' => 12]))
            ->toContain(__('new this week'));
    });

    it('stays silent for a decider with nothing to decide', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        ExpenseReport::factory()->for($treasurer)->create();

        $this->artisan('expense-reports:send-digest')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('is scheduled on sundays at seven in the evening, and only while the feature is on', function (): void {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'expense-reports:send-digest'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 19 * * 0')
            ->and($event->filtersPass(app()))->toBeTrue();

        config(['features.expense_reports' => false]);

        expect($event->filtersPass(app()))->toBeFalse();
    });
});

describe('the dashboard', function (): void {
    it('tells a decider how many reports wait for them', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        ExpenseReport::factory()->count(2)->create();
        ExpenseReport::factory()->for($treasurer)->create();

        $alerts = collect($this->actingAs($treasurer)->get(route('dashboard'))->viewData('alerts'));

        expect($alerts->firstWhere('route', route('admin.treasury.expense-reports'))['label'] ?? null)
            ->toBe('2 notes de frais à traiter');
    });

    it('says nothing to a committee member who only reads', function (): void {
        ExpenseReport::factory()->create();

        $alerts = collect($this->actingAs(User::factory()->isCommitteeMember()->create())->get(route('dashboard'))->viewData('alerts'));

        expect($alerts->pluck('label')->implode(' '))->not->toContain('notes de frais');
    });
});

describe('the purge of what was never paid', function (): void {
    it('deletes the proofs of reports rejected or withdrawn over two years ago, keeping the record', function (): void {
        $oldRejected = ExpenseReport::factory()->rejected()->create(['updated_at' => now()->subYears(2)->subDay()]);
        $recentRejected = ExpenseReport::factory()->rejected()->create(['updated_at' => now()->subYear()]);
        $oldAccepted = ExpenseReport::factory()->accepted()->create(['updated_at' => now()->subYears(3)]);
        foreach ([$oldRejected, $recentRejected, $oldAccepted] as $report) {
            Storage::disk('local')->put("expense-reports/{$report->id}/a.jpg", 'x');
            $report->files()->create(['path' => "expense-reports/{$report->id}/a.jpg", 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('a', 64)]);
        }

        $this->artisan('expense-reports:purge-files')->assertSuccessful();

        Storage::disk('local')->assertMissing("expense-reports/{$oldRejected->id}/a.jpg");
        Storage::disk('local')->assertExists("expense-reports/{$recentRejected->id}/a.jpg");
        Storage::disk('local')->assertExists("expense-reports/{$oldAccepted->id}/a.jpg");

        expect($oldRejected->refresh()->files)->toBeEmpty()
            ->and($oldRejected->files_purged_at)->not->toBeNull()
            ->and($oldRejected->description)->not->toBeEmpty();
    });

    it('runs every night, whether the feature is on or not', function (): void {
        config(['features.expense_reports' => false]);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'expense-reports:purge-files'));

        expect($event)->not->toBeNull()
            ->and($event->filtersPass(app()))->toBeTrue();
    });
});
