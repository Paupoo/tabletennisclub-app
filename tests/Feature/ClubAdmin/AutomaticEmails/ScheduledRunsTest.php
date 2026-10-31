<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsDigestNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsToArchiveNotification;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Notifications\InterclubChangeNotification;
use App\Domains\Competitions\Interclub\Notifications\WeeklySelectionsNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentPaymentReminderNotification;
use App\Mail\QueueStalledMail;
use App\Support\AutomaticEmails\AutomaticEmail;
use App\Support\AutomaticEmails\AutomaticEmailCatalogue;
use App\Support\AutomaticEmails\ScheduledRuns;
use Illuminate\Support\Facades\Process;

function cataloguedEmail(string $source): AutomaticEmail
{
    $email = collect(AutomaticEmailCatalogue::emails())->firstWhere('source', $source);

    expect($email)->not->toBeNull();

    return $email;
}

it('says the cadence of a weekly task in words', function (): void {
    $runs = app(ScheduledRuns::class);

    expect($runs->cadence(cataloguedEmail(WeeklySelectionsNotification::class)))->toBe('Chaque jeudi à 12h00')
        ->and($runs->cadence(cataloguedEmail(ExpenseReportsDigestNotification::class)))->toBe('Chaque dimanche à 19h00');
});

it('says the cadence of the tasks that run several times a day', function (): void {
    $runs = app(ScheduledRuns::class);

    expect($runs->cadence(cataloguedEmail(TournamentPaymentReminderNotification::class)))->toBe('Toutes les heures')
        ->and($runs->cadence(cataloguedEmail(InterclubChangeNotification::class)))->toBe('Toutes les heures de 07h05 à 22h05');
});

it('keeps the catalogue words where the cron alone would mislead', function (): void {
    $archiving = cataloguedEmail(ExpenseReportsToArchiveNotification::class);

    expect(app(ScheduledRuns::class)->cadence($archiving))->toBe($archiving->cadence);
});

it('tells the tasks that run several times a day from the others', function (): void {
    $runs = app(ScheduledRuns::class);

    expect($runs->isContinuous(cataloguedEmail(TournamentPaymentReminderNotification::class)))->toBeTrue()
        ->and($runs->isContinuous(cataloguedEmail(InterclubChangeNotification::class)))->toBeTrue()
        ->and($runs->isContinuous(cataloguedEmail(QueueStalledMail::class)))->toBeTrue()
        ->and($runs->isContinuous(cataloguedEmail(WeeklySelectionsNotification::class)))->toBeFalse()
        ->and($runs->isContinuous(cataloguedEmail(ExpenseReportsToArchiveNotification::class)))->toBeFalse();
});

it('finds the next run of a weekly task', function (): void {
    $this->travelTo('2026-10-31 10:00');

    expect(app(ScheduledRuns::class)->nextRun(cataloguedEmail(WeeklySelectionsNotification::class))?->toDateTimeString())
        ->toBe('2026-11-05 12:00:00');
});

it('skips the occurrences its filters leave out', function (): void {
    // A financial year from July: October is the end of a quarter, November and
    // December are not. The cron alone says 1 November.
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();
    $this->travelTo('2026-10-02 10:00');

    expect(app(ScheduledRuns::class)->nextRun(cataloguedEmail(ExpenseReportsToArchiveNotification::class))?->toDateTimeString())
        ->toBe('2027-01-01 08:00:00');
});

it('has no next run while its domain is switched off', function (): void {
    config(['features.interclubs' => false]);

    expect(app(ScheduledRuns::class)->nextRun(cataloguedEmail(WeeklySelectionsNotification::class)))->toBeNull();
});

it('gives the clock back once it has looked ahead', function (): void {
    $this->travelTo('2026-10-31 10:00');

    app(ScheduledRuns::class)->nextRun(cataloguedEmail(WeeklySelectionsNotification::class));

    expect(now()->toDateTimeString())->toBe('2026-10-31 10:00:00');
});

it('reads the schedule in a web request, where the console kernel never boots', function (): void {
    // The tests boot the console kernel, so they always see the schedule. A
    // web request does not: without the page booting it, the schedule there
    // is empty and every scheduled email would show no cadence at all.
    $boot = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
        . '$app->make(Illuminate\Contracts\Http\Kernel::class)->bootstrap(); ';

    $before = Process::path(base_path())->run([PHP_BINARY, '-r', $boot
        . 'echo count($app->make(Illuminate\Console\Scheduling\Schedule::class)->events());']);
    $after = Process::path(base_path())->run([PHP_BINARY, '-r', $boot
        . 'echo count($app->make(App\Support\AutomaticEmails\ScheduledRuns::class)->eventsByCommand());']);

    expect($before->successful())->toBeTrue($before->errorOutput())
        ->and(trim($before->output()))->toBe('0')
        ->and($after->successful())->toBeTrue($after->errorOutput())
        ->and((int) trim($after->output()))->toBeGreaterThan(10);
});
