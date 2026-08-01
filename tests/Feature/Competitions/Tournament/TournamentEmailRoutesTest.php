<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Illuminate\Support\Facades\URL;

/*
 * The four routed methods of TournamentController, which had no test of their
 * own while the 31 unreachable ones sat next to them. They are the entry points
 * of the signed links sent by e-mail, so they answer to visitors who are not
 * logged in — the least covered and most exposed part of the class.
 */

/*
 * Tournaments carry a price by default, so registering one issues a payment
 * request that reads Club::ourClub()->first()->bic. Without the club row the
 * notification throws, the controller catches it, and the visitor is told the
 * registration failed although it succeeded — worth knowing, but it is a
 * fixture concern here: production always has the row.
 */
beforeEach(function (): void {
    Club::factory()->ownClub()->create();
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function publishedTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PUBLISHED,
        'max_users' => 16,
        'start_date' => now()->addDays(7)->toDateString(),
        'start_time' => '09:30',
        'duration_minutes' => 180,
    ], $overrides));
}

function registerLink(Tournament $tournament, User $user): string
{
    return URL::signedRoute('tournament.register.email', [
        'tournament' => $tournament->id,
        'user' => $user->id,
    ]);
}

function leaveWaitlistLink(Tournament $tournament, User $user): string
{
    return URL::signedRoute('tournament.leave-waitlist.email', [
        'tournament' => $tournament->id,
        'user' => $user->id,
    ]);
}

// ── registerViaEmail ──────────────────────────────────────────────────────────

describe('registerViaEmail', function (): void {
    it('registers a player who clicks the link in the invitation', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();

        $this->get(registerLink($tournament, $user))
            ->assertRedirect(route('tournament.registration.confirmed', $tournament))
            ->assertSessionHas('registration_status', 'registered');

        expect($tournament->users()->where('users.id', $user->id)->exists())->toBeTrue();
    });

    it('tells a player already registered that they are on the list, without duplicating', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

        $this->get(registerLink($tournament, $user))
            ->assertSessionHas('registration_status', 'registered')
            ->assertSessionHas('already_on_list', true);

        expect($tournament->users()->where('users.id', $user->id)->count())->toBe(1);
    });

    it('returns the waitlist position to a player still waiting', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'waiting',
            'waitlist_position' => 3,
        ]);

        $this->get(registerLink($tournament, $user))
            ->assertSessionHas('registration_status', 'waiting')
            ->assertSessionHas('waitlist_position', 3)
            ->assertSessionHas('already_on_list', true);
    });

    /*
     * The promotion mail offers a spot and the click is the confirmation, so this
     * is the one branch that writes: it turns spot_offered into registered and
     * drops the deadline the scheduler would otherwise use to expire the offer.
     */
    it('confirms a spot offered to a promoted player and clears the deadline', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'spot_offered',
            'confirmation_deadline' => now()->addDay(),
        ]);

        $this->get(registerLink($tournament, $user))
            ->assertSessionHas('registration_status', 'registered');

        $pivot = $tournament->users()->where('users.id', $user->id)->first()->pivot;

        expect($pivot->registration_status)->toBe('registered')
            ->and($pivot->confirmation_deadline)->toBeNull();
    });

    it('refuses to register when the tournament is not published', function (): void {
        $tournament = publishedTournament(['status' => TournamentStatusEnum::DRAFT]);
        $user = User::factory()->create();

        $this->get(registerLink($tournament, $user))
            ->assertRedirect(route('tournament.registration.confirmed', $tournament))
            ->assertSessionHas('error');

        expect($tournament->users()->where('users.id', $user->id)->exists())->toBeFalse();
    });

    it('rejects an unsigned link', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();

        $this->get(route('tournament.register.email', [
            'tournament' => $tournament->id,
            'user' => $user->id,
        ]))->assertForbidden();

        expect($tournament->users()->where('users.id', $user->id)->exists())->toBeFalse();
    });
})->group('tournament');

// ── leaveWaitlistViaEmail ─────────────────────────────────────────────────────

describe('leaveWaitlistViaEmail', function (): void {
    it('takes a waiting player off the list', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'waiting',
            'waitlist_position' => 1,
        ]);

        $this->get(leaveWaitlistLink($tournament, $user))
            ->assertRedirect(route('tournament.registration.confirmed', $tournament))
            ->assertSessionHas('registration_status', 'left_waitlist');

        $pivot = $tournament->users()->where('users.id', $user->id)->first()?->pivot;

        expect($pivot?->registration_status)->not->toBe('waiting');
    });

    // Clicking an old mail twice must not report a failure to the visitor.
    it('stays silent when the player is not on the waitlist', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();

        $this->get(leaveWaitlistLink($tournament, $user))
            ->assertRedirect(route('tournament.registration.confirmed', $tournament))
            ->assertSessionHas('registration_status', 'left_waitlist');
    });

    it('rejects an unsigned link', function (): void {
        $tournament = publishedTournament();
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'waiting']);

        $this->get(route('tournament.leave-waitlist.email', [
            'tournament' => $tournament->id,
            'user' => $user->id,
        ]))->assertForbidden();

        $pivot = $tournament->users()->where('users.id', $user->id)->first()->pivot;

        expect($pivot->registration_status)->toBe('waiting');
    });
})->group('tournament');

// ── registrationConfirmed ─────────────────────────────────────────────────────

describe('registrationConfirmed', function (): void {
    it('renders for a visitor who is not logged in', function (): void {
        $tournament = publishedTournament(['name' => 'Tournoi de printemps']);

        $this->get(route('tournament.registration.confirmed', $tournament))
            ->assertOk()
            ->assertSee('Tournoi de printemps');
    });

    it('passes the status carried by the session to the view', function (): void {
        $tournament = publishedTournament();

        $this->withSession(['registration_status' => 'waiting', 'waitlist_position' => 2])
            ->get(route('tournament.registration.confirmed', $tournament))
            ->assertOk()
            ->assertViewHas('registrationStatus', 'waiting')
            ->assertViewHas('waitlistPosition', 2);
    });
})->group('tournament');

// ── downloadIcal ──────────────────────────────────────────────────────────────

describe('downloadIcal', function (): void {
    it('serves a calendar file named after the tournament', function (): void {
        $tournament = publishedTournament(['name' => 'Tournoi de printemps']);

        $response = $this->get(route('tournament.calendar.ical', $tournament));

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="tournoi-de-printemps.ics"');
    });

    it('encodes the start and end as local times carrying their timezone', function (): void {
        $tournament = publishedTournament([
            'start_date' => '2026-09-12',
            'start_time' => '09:30',
            'duration_minutes' => 90,
        ]);

        $body = $this->get(route('tournament.calendar.ical', $tournament))->getContent();

        expect($body)
            ->toContain('BEGIN:VCALENDAR')
            ->toContain('BEGIN:VEVENT')
            ->toContain('UID:tournament-' . $tournament->id . '@cttottigniesblocry.be')
            ->toContain('DTSTART;TZID=Europe/Brussels:20260912T093000')
            ->toContain('DTEND;TZID=Europe/Brussels:20260912T110000')
            ->toContain('END:VCALENDAR');
    });

    it('falls back to three hours when no duration is set', function (): void {
        $tournament = publishedTournament([
            'start_date' => '2026-09-12',
            'start_time' => '09:00',
            'duration_minutes' => 0,
        ]);

        expect($this->get(route('tournament.calendar.ical', $tournament))->getContent())
            ->toContain('DTEND;TZID=Europe/Brussels:20260912T120000');
    });

    // RFC 5545 §3.1: a content line longer than 75 octets continues on a line
    // starting with a single space.
    it('folds a long summary onto continuation lines', function (): void {
        $tournament = publishedTournament([
            'name' => str_repeat('Tournoi interclubs de printemps ', 5),
        ]);

        $body = $this->get(route('tournament.calendar.ical', $tournament))->getContent();

        expect($body)->toContain("\r\n ");
    });

    it('keeps every line within 75 octets even with accented names', function (): void {
        $tournament = publishedTournament([
            'name' => str_repeat('Tournoi de Noël à Ottignies ', 5),
        ]);

        $body = $this->get(route('tournament.calendar.ical', $tournament))->getContent();

        foreach (explode("\r\n", $body) as $line) {
            expect(strlen($line))->toBeLessThanOrEqual(76); // 75 octets + the folding space
        }
    });

    it('never splits a multi-byte character across a fold', function (): void {
        $tournament = publishedTournament([
            'name' => str_repeat('éàçüö', 40),
        ]);

        $body = $this->get(route('tournament.calendar.ical', $tournament))->getContent();

        expect(mb_check_encoding($body, 'UTF-8'))->toBeTrue();
    });

    /*
     * RFC 5545 §3.3.11: a comma is escaped as \, and a backslash as \\. Escaping
     * the backslash last used to double the ones the other rules had just added,
     * turning a comma into `\\,` — which a calendar reads as an escaped
     * backslash followed by a value separator, truncating the summary.
     */
    it('escapes commas, semicolons and backslashes exactly once', function (): void {
        $tournament = publishedTournament(['name' => 'Tournoi, niveau B; salle A\\B']);

        $body = $this->get(route('tournament.calendar.ical', $tournament))->getContent();

        expect($body)->toContain('SUMMARY:Tournoi\\, niveau B\\; salle A\\\\B');
    });
})->group('tournament');
