<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsDigestNotification;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsToArchiveNotification;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Notifications\CaptainLineupReminderNotification;
use App\Domains\Competitions\Interclub\Notifications\WeeklySelectionsNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentInvitationNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentPaymentReminderNotification;
use App\Domains\Shared\Enums\EmailTrigger;
use App\Support\AutomaticEmails\AutomaticEmail;
use App\Support\AutomaticEmails\AutomaticEmailAgenda;
use App\Support\AutomaticEmails\AutomaticEmailCatalogue;

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['fiscal_year_start_month' => 7]);
    Club::forgetOwnClub();
});

/**
 * @param  array<int, array{email: AutomaticEmail}>  $rows
 * @return array<int, class-string>
 */
function agendaSources(array $rows): array
{
    return array_map(fn (array $row): string => $row['email']->source, $rows);
}

it('puts the tasks that run several times a day apart, undated', function (): void {
    $agenda = app(AutomaticEmailAgenda::class)->build(AutomaticEmailCatalogue::emails(), now()->setDate(2026, 10, 31)->setTime(10, 0));

    expect(agendaSources($agenda['continuous']))->toContain(TournamentPaymentReminderNotification::class)
        ->not->toContain(WeeklySelectionsNotification::class);
});

it('lays the coming week out day by day, each email once at its next run', function (): void {
    // Saturday: the Sunday evening reminders come first, in their order, the
    // Thursday selections later. The daily tasks are there on Sunday too.
    $agenda = app(AutomaticEmailAgenda::class)->build(AutomaticEmailCatalogue::emails(), now()->setDate(2026, 10, 31)->setTime(10, 0));

    $sunday = collect($agenda['days'])->first(fn (array $day): bool => $day['date']->toDateString() === '2026-11-01');
    $thursday = collect($agenda['days'])->first(fn (array $day): bool => $day['date']->toDateString() === '2026-11-05');

    expect(array_slice(agendaSources($sunday['runs']), -2))->toBe([CaptainLineupReminderNotification::class, ExpenseReportsDigestNotification::class])
        ->and(collect($sunday['runs'])->map(fn (array $run): string => $run['at']->format('H:i'))->all())->toBe(collect($sunday['runs'])->map(fn (array $run): string => $run['at']->format('H:i'))->sort()->values()->all())
        ->and(agendaSources($thursday['runs']))->toContain(WeeklySelectionsNotification::class)
        ->and(collect($agenda['days'])->flatMap(fn (array $day): array => agendaSources($day['runs']))->duplicates())->toBeEmpty();
});

it('keeps what leaves beyond the week for later, with its date', function (): void {
    $agenda = app(AutomaticEmailAgenda::class)->build(AutomaticEmailCatalogue::emails(), now()->setDate(2026, 10, 31)->setTime(10, 0));

    $archiving = collect($agenda['later'])->first(fn (array $row): bool => $row['email']->source === ExpenseReportsToArchiveNotification::class);

    expect($archiving['at']?->toDateTimeString())->toBe('2027-01-01 08:00:00');
});

it('keeps a switched-off domain on the page, undated', function (): void {
    config(['features.interclubs' => false]);

    $agenda = app(AutomaticEmailAgenda::class)->build(AutomaticEmailCatalogue::emails(), now()->setDate(2026, 10, 31)->setTime(10, 0));

    $selections = collect($agenda['later'])->first(fn (array $row): bool => $row['email']->source === WeeklySelectionsNotification::class);

    expect($selections)->not->toBeNull()
        ->and($selections['at'])->toBeNull()
        ->and($selections['email']->isOff())->toBeTrue();
});

it('groups the emails an event or an action sets off by domain', function (): void {
    $agenda = app(AutomaticEmailAgenda::class)->build(AutomaticEmailCatalogue::emails());

    $tournaments = collect($agenda['domains'])->firstWhere('name', 'Tournois');

    expect(array_map(fn (AutomaticEmail $email): string => $email->source, $tournaments['emails']))->toContain(TournamentInvitationNotification::class)
        ->and(collect($agenda['domains'])->flatMap(fn (array $domain): array => $domain['emails'])
            ->filter(fn ($email): bool => $email->trigger === EmailTrigger::Scheduled))->toBeEmpty();
});
