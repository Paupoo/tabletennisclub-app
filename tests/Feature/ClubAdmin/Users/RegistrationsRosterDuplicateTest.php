<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations');

const ROSTER_COMPONENT = 'pages::club-admin.users.registrations';

/*
 * Issue #29: a member had their affiliation rejected, submitted a new one, and
 * then appeared twice on the season roster — the cancelled row and the new one.
 *
 * Rejecting keeps the row on purpose (the history, and the notification that
 * went with it), and resubmitting is meant to be allowed: scopeAffiliated
 * counts pending/confirmed/paid only, so a cancelled affiliation leaves the
 * member free to register again. What was missing is the other half of that
 * rule — a cancelled affiliation never stood, so it does not belong on the
 * roster either.
 */

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
    actingAs(User::factory()->isAdmin()->create());
});

function rowsFor(string $statusFilter = ''): Collection
{
    return collect(
        Livewire::test(ROSTER_COMPONENT)
            ->set('statusFilter', $statusFilter)
            ->viewData('registrations')
            ->items()
    );
}

/** The member of #29: rejected once, registered again. */
function memberRejectedThenBack(Season $season): User
{
    $member = User::factory()->create(['first_name' => 'Gilles', 'last_name' => 'Ledoublé']);

    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $season->id,
        'status' => 'cancelled',
    ]);
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $season->id,
        'status' => 'pending',
    ]);

    return $member;
}

it('lists a member who registered again after a rejection only once', function (): void {
    $member = memberRejectedThenBack($this->season);

    $rows = rowsFor()->filter(fn (object $row): bool => str_contains($row->name, 'Ledoublé'));

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->status)->toBe('pending');
});

it('still lets the committee find the cancelled one on purpose', function (): void {
    memberRejectedThenBack($this->season);

    $rows = rowsFor('cancelled')->filter(fn (object $row): bool => str_contains($row->name, 'Ledoublé'));

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->status)->toBe('cancelled');
});

/*
 * Total sat above the four cards that are supposed to make it up, because a
 * cancellation was counted without having a card of its own.
 */
it('counts the roster the way it lists it', function (): void {
    memberRejectedThenBack($this->season);

    $stats = Livewire::test(ROSTER_COMPONENT)->viewData('stats');

    expect($stats['total'])->toBe(1)
        ->and($stats['pending'])->toBe(1)
        ->and($stats['total'])->toBe(
            $stats['pending'] + $stats['confirmed'] + $stats['paid'] + $stats['refunded']
        );
});

it('leaves a member with a single cancelled affiliation off the roster', function (): void {
    $member = User::factory()->create(['last_name' => 'Parti']);
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'cancelled',
    ]);

    expect(rowsFor()->filter(fn (object $row): bool => str_contains($row->name, 'Parti')))
        ->toBeEmpty();
});
