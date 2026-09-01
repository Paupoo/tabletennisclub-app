<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Services\UserCalendarService;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Support\Carbon;

pest()->group('tournaments');

/*
 * `start_date` and `start_time` are separate columns and only the second is
 * maintained: the wizard writes the day into one and the hour into the other,
 * so a tournament it created carries midnight in `start_date`. Three pages and
 * the calendar feed read that midnight and announced a ten o'clock tournament
 * as starting at 00:00.
 */
it('takes the day from start_date and the hour from start_time', function (): void {
    $tournament = Tournament::factory()->create([
        'start_date' => '2026-09-26 00:00:00',
        'start_time' => '10:00:00',
    ]);

    expect($tournament->startsAt()?->format('Y-m-d H:i'))->toBe('2026-09-26 10:00')
        ->and($tournament->hasKnownStartTime())->toBeTrue();
});

/*
 * Rows that predate the `start_time` column carry their hour in `start_date`.
 * Reading the hour from the new column alone would have moved them all to
 * midnight, which is the same bug in the other direction.
 */
it('keeps the hour a row carries in start_date when start_time is empty', function (): void {
    $tournament = Tournament::factory()->create([
        'start_date' => '2026-09-05 09:00:00',
        'start_time' => null,
    ]);

    expect($tournament->startsAt()?->format('Y-m-d H:i'))->toBe('2026-09-05 09:00')
        ->and($tournament->hasKnownStartTime())->toBeTrue();
});

/*
 * No hour anywhere is not an hour of midnight. Showing 00:00 is worse than
 * showing nothing: a member reads it as a real time.
 */
it('reports no known hour when neither column carries one', function (): void {
    $tournament = Tournament::factory()->create([
        'start_date' => '2026-09-26 00:00:00',
        'start_time' => null,
    ]);

    expect($tournament->hasKnownStartTime())->toBeFalse()
        ->and($tournament->startsAt()?->format('Y-m-d H:i'))->toBe('2026-09-26 00:00');
});

it('carries the real hour into the member calendar, not midnight', function (): void {
    $member = User::factory()->create();
    $tournament = Tournament::factory()->create([
        'name' => 'Tournoi des crêpes',
        'status' => TournamentStatusEnum::PUBLISHED,
        'start_date' => '2026-09-26 00:00:00',
        'start_time' => '10:00:00',
        'end_date' => null,
    ]);
    $tournament->users()->attach($member->id, ['registration_status' => 'confirmed']);

    $events = app(UserCalendarService::class)->eventsFor(
        $member,
        showAllEvents: true,
        from: Carbon::parse('2026-09-01'),
        to: Carbon::parse('2026-10-31'),
    );

    $row = $events->firstWhere('title', 'Tournoi des crêpes');

    expect($row)->not->toBeNull()
        ->and($row['startDateTime'])->toBe('2026-09-26 10:00:00');
});

it('exports the real hour in the tournament .ics', function (): void {
    $tournament = Tournament::factory()->create([
        'start_date' => '2026-09-26 00:00:00',
        'start_time' => '10:00:00',
        'duration_minutes' => 240,
    ]);

    $body = $this->get(route('tournament.calendar.ical', $tournament))->getContent();

    expect($body)->toContain('DTSTART;TZID=Europe/Brussels:20260926T100000')
        ->and($body)->toContain('DTEND;TZID=Europe/Brussels:20260926T140000');
});
