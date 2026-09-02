<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Domains\Shared\States\Tournament\States\CancelledState;
use App\Domains\Shared\States\Tournament\States\ClosedState;
use App\Domains\Shared\States\Tournament\States\DraftState;
use App\Domains\Shared\States\Tournament\States\LockedState;
use App\Domains\Shared\States\Tournament\States\PendingState;
use App\Domains\Shared\States\Tournament\States\PublishedState;
use App\Domains\Shared\States\Tournament\States\SetUpState;
use App\Domains\Shared\States\Tournament\TournamentStateFactory;
use App\Domains\Shared\States\Tournament\TournamentStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── The factory hands back the state that matches the status ──────────────────

it('maps every status to the state class that claims it', function (TournamentStatusEnum $status, string $expected): void {
    $state = TournamentStateFactory::create($status);

    expect($state)->toBeInstanceOf($expected)
        ->and($state->getStatus())->toBe($status);
})->with([
    'draft' => [TournamentStatusEnum::DRAFT, DraftState::class],
    'locked' => [TournamentStatusEnum::LOCKED, LockedState::class],
    'published' => [TournamentStatusEnum::PUBLISHED, PublishedState::class],
    'setup' => [TournamentStatusEnum::SETUP, SetUpState::class],
    'pending' => [TournamentStatusEnum::PENDING, PendingState::class],
    'closed' => [TournamentStatusEnum::CLOSED, ClosedState::class],
    'cancelled' => [TournamentStatusEnum::CANCELLED, CancelledState::class],
]);

// ── A transition has to survive in the database, not only in memory ───────────

it('persists a transition so a fresh read from the database sees it', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::SETUP]);

    new TournamentStateMachine($tournament)->publish();

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::PUBLISHED);
});

it('leaves the machine pointing at the state it just moved into', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::SETUP]);
    $machine = new TournamentStateMachine($tournament);

    $machine->publish();

    expect($machine->getCurrentState())->toBeInstanceOf(PublishedState::class);
});

it('refuses a transition the current state does not allow', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::CLOSED]);

    expect(fn (): mixed => new TournamentStateMachine($tournament)->publish())
        ->toThrow(InvalidArgumentException::class);

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::CLOSED);
});

/**
 * A tournament match carrying nothing but the status the guard reads.
 *
 * Pools and tables are irrelevant here and their factory defaults point at
 * rows that do not exist, so both are nulled out — same shape as the other
 * tournament tests.
 */
function matchWithStatus(Tournament $tournament, string $status): TournamentMatch
{
    return TournamentMatch::factory()->create([
        'tournament_id' => $tournament->id,
        'pool_id' => null,
        'table_id' => null,
        'status' => $status,
    ]);
}

// ── Business guards: a tournament under way cannot be walked back ─────────────

it('refuses to cancel a running tournament once a match has started', function (string $matchStatus): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
    matchWithStatus($tournament, $matchStatus);

    expect(fn (): mixed => new TournamentStateMachine($tournament)->cancel())
        ->toThrow(LogicException::class);

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::PENDING);
})->with(['in_progress', 'completed']);

it('still cancels a running tournament while every match is only scheduled', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
    matchWithStatus($tournament, 'scheduled');

    new TournamentStateMachine($tournament)->cancel();

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::CANCELLED);
});

it('refuses to close a tournament while a match is left unplayed', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
    matchWithStatus($tournament, 'in_progress');

    expect(fn (): mixed => new TournamentStateMachine($tournament)->close())
        ->toThrow(LogicException::class);

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::PENDING);
});

it('closes a tournament once every match is completed', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
    matchWithStatus($tournament, 'completed');

    new TournamentStateMachine($tournament)->close();

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::CLOSED);
});

// ── The transition table describes the journey the wizard actually walks ──────

it('allows exactly the transitions the wizard walks', function (TournamentStatusEnum $from, array $expected): void {
    expect(TournamentStateFactory::create($from)->getAllowedTransitions())
        ->toEqualCanonicalizing($expected);
})->with([
    'draft is validated or abandoned — it is never published directly' => [
        TournamentStatusEnum::DRAFT,
        [TournamentStatusEnum::LOCKED, TournamentStatusEnum::CANCELLED],
    ],
    'locked opens the registrations, or cancels' => [
        TournamentStatusEnum::LOCKED,
        [TournamentStatusEnum::PUBLISHED, TournamentStatusEnum::CANCELLED],
    ],
    'published closes for setup, unpublishes, or cancels' => [
        TournamentStatusEnum::PUBLISHED,
        [TournamentStatusEnum::DRAFT, TournamentStatusEnum::SETUP, TournamentStatusEnum::CANCELLED],
    ],
    'setup reopens, launches, or cancels' => [
        TournamentStatusEnum::SETUP,
        [TournamentStatusEnum::PUBLISHED, TournamentStatusEnum::PENDING, TournamentStatusEnum::CANCELLED],
    ],
    'pending goes back to setup, closes, or cancels' => [
        TournamentStatusEnum::PENDING,
        [TournamentStatusEnum::SETUP, TournamentStatusEnum::CLOSED, TournamentStatusEnum::CANCELLED],
    ],
    'closed is terminal' => [TournamentStatusEnum::CLOSED, []],
    'cancelled is terminal' => [TournamentStatusEnum::CANCELLED, []],
]);

it('opens the registrations of a locked tournament, which is what option A delivered', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::LOCKED]);

    new TournamentStateMachine($tournament)->publish();

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::PUBLISHED);
});

it('locks a validated draft', function (): void {
    $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);

    new TournamentStateMachine($tournament)->lock();

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::LOCKED);
});

it('cancels from every status the committee can still back out of', function (TournamentStatusEnum $from): void {
    $tournament = Tournament::factory()->create(['status' => $from]);

    new TournamentStateMachine($tournament)->cancel();

    expect(Tournament::findOrFail($tournament->id)->status)
        ->toBe(TournamentStatusEnum::CANCELLED);
})->with([
    'draft' => TournamentStatusEnum::DRAFT,
    'locked' => TournamentStatusEnum::LOCKED,
    'published' => TournamentStatusEnum::PUBLISHED,
    'setup' => TournamentStatusEnum::SETUP,
]);

// ── The predicates the wizard reads instead of comparing enum values ──────────

it('names the only status where members can enter', function (): void {
    $open = array_values(array_filter(
        TournamentStatusEnum::cases(),
        fn (TournamentStatusEnum $s): bool => TournamentStateFactory::create($s)->canRegisterUsers(),
    ));

    expect($open)->toEqualCanonicalizing([TournamentStatusEnum::PUBLISHED]);
});

it('names the only status where pools can be built', function (): void {
    $building = array_values(array_filter(
        TournamentStatusEnum::cases(),
        fn (TournamentStatusEnum $s): bool => TournamentStateFactory::create($s)->canCreatePools(),
    ));

    expect($building)->toEqualCanonicalizing([TournamentStatusEnum::SETUP]);
});

/* Locked has never been opened; setup was opened then closed. Both reopen. */
it('names the statuses whose registrations can be opened', function (): void {
    $openable = array_values(array_filter(
        TournamentStatusEnum::cases(),
        fn (TournamentStatusEnum $s): bool => TournamentStateFactory::create($s)
            ->canTransitionTo(TournamentStatusEnum::PUBLISHED),
    ));

    expect($openable)->toEqualCanonicalizing([TournamentStatusEnum::LOCKED, TournamentStatusEnum::SETUP]);
});

/* Name and price stop being editable the moment the contract is validated. */
it('names the statuses whose contract is frozen', function (): void {
    $frozen = array_values(array_filter(
        TournamentStatusEnum::cases(),
        fn (TournamentStatusEnum $s): bool => TournamentStateFactory::create($s)->hasLockedContract(),
    ));

    expect($frozen)->toEqualCanonicalizing([
        TournamentStatusEnum::LOCKED,
        TournamentStatusEnum::PUBLISHED,
        TournamentStatusEnum::SETUP,
        TournamentStatusEnum::PENDING,
        TournamentStatusEnum::CLOSED,
    ]);
});

/* Launched means play has started: the wizard is done, the live centre owns it. */
it('names the statuses of a tournament already launched', function (): void {
    $launched = array_values(array_filter(
        TournamentStatusEnum::cases(),
        fn (TournamentStatusEnum $s): bool => TournamentStateFactory::create($s)->hasBeenLaunched(),
    ));

    expect($launched)->toEqualCanonicalizing([TournamentStatusEnum::PENDING, TournamentStatusEnum::CLOSED]);
});

// ── The two axes that used to share the word "published" (issue #35) ──────────

describe('the two published axes', function (): void {
    /*
     * Registrations open is about members signing up; being on the public
     * website is about the article. They are independent, and calling both
     * "published" is what made #35 unreadable.
     */
    it('reports open registrations from the status alone', function (): void {
        expect(Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED])->registrationsAreOpen())
            ->toBeTrue()
            ->and(Tournament::factory()->create(['status' => TournamentStatusEnum::LOCKED])->registrationsAreOpen())
            ->toBeFalse();
    });

    it('reports being on the public website from the article, not the status', function (): void {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);

        expect($tournament->isOnPublicWebsite())->toBeFalse();

        EventPost::factory()->create([
            'eventable_id' => $tournament->id,
            'eventable_type' => Tournament::class,
            'status' => EventPostStatusEnum::PUBLISHED,
        ]);

        expect($tournament->fresh()->isOnPublicWebsite())->toBeTrue();
    });

    it('does not count a drafted article as being on the public website', function (): void {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
        EventPost::factory()->create([
            'eventable_id' => $tournament->id,
            'eventable_type' => Tournament::class,
            'status' => EventPostStatusEnum::DRAFT,
        ]);

        expect($tournament->fresh()->isOnPublicWebsite())->toBeFalse();
    });

    it('scopes a query to the tournaments members can enter', function (): void {
        $open = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
        Tournament::factory()->create(['status' => TournamentStatusEnum::SETUP]);

        expect(Tournament::registrationsOpen()->pluck('id')->all())->toBe([$open->id]);
    });
});
