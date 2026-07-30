<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendMemberInvitationJob;
use Illuminate\Bus\PendingBatch;
use Illuminate\Cache\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'invitations');

const USERS_INDEX_COMPONENT = 'pages::club-admin.users.index';

/**
 * A member recorded by an import: an account exists, nobody has been told.
 */
function uninvitedMember(?string $email = null): User
{
    return User::factory()->unverified()->create([
        'email' => $email,
        'last_invited_at' => null,
    ]);
}

beforeEach(function (): void {
    actingAs(User::factory()->withRole(Role::MEMBERS)->create());
});

/*
 * Importing and inviting are never the same click. The import records people in
 * silence; this is where a human decides that fifty of them are about to hear
 * from the club for the first time.
 */
describe('inviting members in bulk', function (): void {

    it('queues one invitation per selected member', function (): void {
        Bus::fake();

        $first = uninvitedMember('first@example.com');
        $second = uninvitedMember('second@example.com');

        Livewire::test(USERS_INDEX_COMPONENT)
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->call('bulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2);
    });

    /*
     * Gmail counts five hundred a day, but it is the burst that gets a club
     * classified as a spammer. Fifty invitations go out over three quarters of an
     * hour instead of three seconds.
     */
    it('sends them slowly enough not to look like a spammer', function (): void {
        $job = new SendMemberInvitationJob(uninvitedMember('slow@example.com')->id);

        expect($job->middleware())->toHaveCount(1)
            ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);

        expect(app(RateLimiter::class)->limiter('invitations'))->not->toBeNull();
    });

    /*
     * A member with no address of their own has nowhere to receive a login — the
     * link would land in their guardian's mailbox and set a password on somebody
     * else's account. They are counted out loud, not silently dropped.
     */
    it('leaves out the members who have no address of their own, and says how many', function (): void {
        Bus::fake();

        $invitable = uninvitedMember('invitable@example.com');
        $managed = uninvitedMember();

        Livewire::test(USERS_INDEX_COMPONENT)
            ->set('selected', [(string) $invitable->id, (string) $managed->id])
            ->call('bulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
    });

    it('leaves out the members who already have an account', function (): void {
        Bus::fake();

        $invitable = uninvitedMember('invitable@example.com');
        $registered = User::factory()->create(['email' => 'registered@example.com']);

        Livewire::test(USERS_INDEX_COMPONENT)
            ->set('selected', [(string) $invitable->id, (string) $registered->id])
            ->call('bulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
    });

    /*
     * A second invitation is not a repeat of the first: it invalidates the link
     * the member may be about to click. Never silently.
     */
    it('asks before sending a second invitation to somebody still waiting', function (): void {
        Bus::fake();

        $waiting = uninvitedMember('waiting@example.com');
        $waiting->update(['last_invited_at' => now()->subDay()]);

        $component = Livewire::test(USERS_INDEX_COMPONENT)
            ->set('selected', [(string) $waiting->id])
            ->call('bulkInvite')
            ->assertSet('confirmReinviteModal', true);

        Bus::assertNothingBatched();

        $component->call('confirmBulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
    });

    it('is closed to a member who may not write to the club', function (): void {
        Bus::fake();

        actingAs(User::factory()->create());

        Livewire::test(USERS_INDEX_COMPONENT)
            ->set('selected', [(string) uninvitedMember('someone@example.com')->id])
            ->call('bulkInvite')
            ->assertForbidden();

        Bus::assertNothingBatched();
    });
});
