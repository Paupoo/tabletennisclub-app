<?php

declare(strict_types=1);

use App\Actions\User\SendInvitationAction;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;

/*
 * Managed accounts: members the club records but who cannot log in on their own —
 * children too young to own a mailbox, siblings affiliated under one parent's
 * address, adults who simply have no email. Their `email` is null, and they are
 * reached through their guardian.
 */

describe('reaching a member', function (): void {

    it('uses the member own address when they have one', function (): void {
        $member = User::factory()->create(['email' => 'member@example.com']);

        expect($member->contactEmail())->toBe('member@example.com');
    });

    it('falls back to the guardian address when the member has none', function (): void {
        $member = User::factory()->create(['email' => null]);
        $member->guardians()->attach(Guardian::factory()->create(['email' => 'guardian@example.com']));

        expect($member->fresh()->contactEmail())->toBe('guardian@example.com');
    });

    it('ignores guardians who have no address either', function (): void {
        $member = User::factory()->create(['email' => null]);
        $member->guardians()->attach(Guardian::factory()->create(['email' => null]));
        $member->guardians()->attach(Guardian::factory()->create(['email' => 'reachable@example.com']));

        expect($member->fresh()->contactEmail())->toBe('reachable@example.com');
    });

    it('reports nobody to write to when neither the member nor a guardian has an address', function (): void {
        $member = User::factory()->create(['email' => null]);

        expect($member->contactEmail())->toBeNull();
    });

    /*
     * The day the member gets an address of their own the channel switches by
     * itself: no migration, no account merge, no guardian link to sever.
     */
    it('switches back to the member as soon as they get an address', function (): void {
        $member = User::factory()->create(['email' => null]);
        $member->guardians()->attach(Guardian::factory()->create(['email' => 'guardian@example.com']));

        $member->update(['email' => 'grown.up@example.com']);

        expect($member->fresh()->contactEmail())->toBe('grown.up@example.com');
    });
});

/*
 * Notifications are messages, so they follow the contact address rather than the
 * login. Overriding the routing in one place covers every notification the
 * application sends without touching them one by one.
 */
describe('routing notifications', function (): void {

    it('routes to the member own address when they have one', function (): void {
        $member = User::factory()->create(['email' => 'member@example.com']);

        expect($member->routeNotificationForMail())->toBe('member@example.com');
    });

    it('routes to the guardian when the member has no address', function (): void {
        $member = User::factory()->create(['email' => null]);
        $member->guardians()->attach(Guardian::factory()->create(['email' => 'guardian@example.com']));

        expect($member->fresh()->routeNotificationForMail())->toBe('guardian@example.com');
    });
});

/*
 * An invitation is not a message, it is the handover of a login. It therefore
 * goes to the member's own address and never to their guardian's: a guardian
 * receiving it would be setting a password on an account that is not theirs.
 * A member with no address of their own simply cannot be invited yet.
 */
describe('inviting a member', function (): void {

    it('sends the invitation to a member who has an address', function (): void {
        Mail::fake();
        $member = User::factory()->create(['email' => 'member@example.com']);

        $sent = SendInvitationAction::handle($member);

        expect($sent)->toBeTrue();
        Mail::assertQueued(InviteNewUserMail::class);
        expect($member->fresh()->last_invited_at)->not->toBeNull();
    });

    it('refuses to invite a member who has no address of their own', function (): void {
        Mail::fake();
        $member = User::factory()->create(['email' => null]);
        $member->guardians()->attach(Guardian::factory()->create(['email' => 'guardian@example.com']));

        $sent = SendInvitationAction::handle($member->fresh());

        expect($sent)->toBeFalse();
        Mail::assertNothingQueued();
        expect($member->fresh()->last_invited_at)->toBeNull();
    });
});
