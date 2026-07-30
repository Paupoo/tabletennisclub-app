<?php

declare(strict_types=1);

use App\Actions\User\SendInvitationAction;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

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
 * How the secretary finds the members an import brought in and has not written to
 * yet. The states are the ones User::invitationStatus() already names — no new
 * status was introduced for the import, and none was needed.
 */
describe('filtering members by where they stand with their account', function (): void {

    it('finds the members nobody has written to yet', function (): void {
        $untouched = User::factory()->create(['last_invited_at' => null, 'email_verified_at' => null]);
        User::factory()->create(['last_invited_at' => now(), 'email_verified_at' => null]);
        User::factory()->create(['email_verified_at' => now()]);

        expect(User::withInvitationState('not_invited')->pluck('id')->all())->toBe([$untouched->id]);
    });

    it('finds the members still sitting on a live invitation', function (): void {
        $invited = User::factory()->create(['last_invited_at' => now()->subDay(), 'email_verified_at' => null]);
        User::factory()->create([
            'last_invited_at' => now()->subDays(User::INVITATION_LINK_VALIDITY_DAYS + 1),
            'email_verified_at' => null,
        ]);

        expect(User::withInvitationState('pending')->pluck('id')->all())->toBe([$invited->id]);
    });

    it('finds the members whose invitation went stale', function (): void {
        $stale = User::factory()->create([
            'last_invited_at' => now()->subDays(User::INVITATION_LINK_VALIDITY_DAYS + 1),
            'email_verified_at' => null,
        ]);
        User::factory()->create(['last_invited_at' => now()->subDay(), 'email_verified_at' => null]);

        expect(User::withInvitationState('expired')->pluck('id')->all())->toBe([$stale->id]);
    });

    it('finds the members who have an account of their own', function (): void {
        $registered = User::factory()->create(['email_verified_at' => now()]);
        User::factory()->create(['last_invited_at' => now(), 'email_verified_at' => null]);

        expect(User::withInvitationState('active')->pluck('id')->all())->toBe([$registered->id]);
    });

    it('agrees with the badge shown on each member', function (): void {
        $members = [
            'not_invited' => User::factory()->create(['last_invited_at' => null, 'email_verified_at' => null]),
            'pending' => User::factory()->create(['last_invited_at' => now(), 'email_verified_at' => null]),
            'active' => User::factory()->create(['email_verified_at' => now()]),
        ];

        foreach ($members as $state => $member) {
            expect($member->invitationStatus())->toBe($state)
                ->and(User::withInvitationState($state)->pluck('id')->all())->toContain($member->id);
        }
    });
});

/*
 * A child reached through a parent is the arrangement working as intended. An
 * adult in the same position is a loose end: nobody chose it for them, and only
 * the secretary can close it, by asking them for an address. Nothing happens on
 * their eighteenth birthday — this is what makes them findable.
 */
describe('finding the adults who still have no address of their own', function (): void {

    it('lists an adult member with no address', function (): void {
        $adult = User::factory()->create(['email' => null, 'birthdate' => now()->subYears(30)]);

        expect(User::adultWithoutOwnAddress()->pluck('id')->all())->toBe([$adult->id]);
    });

    it('leaves out the children, who are meant to be reached through a guardian', function (): void {
        User::factory()->create(['email' => null, 'birthdate' => now()->subYears(12)]);

        expect(User::adultWithoutOwnAddress()->count())->toBe(0);
    });

    it('leaves out the adults who have one', function (): void {
        User::factory()->create(['email' => 'grown.up@example.com', 'birthdate' => now()->subYears(30)]);

        expect(User::adultWithoutOwnAddress()->count())->toBe(0);
    });

    /*
     * A member recorded without a birthdate is not a child until proven so: the
     * roster holds several, and hiding them here would hide the very accounts
     * nobody is looking after.
     */
    it('counts a member of unknown age as an adult', function (): void {
        $unknown = User::factory()->create(['email' => null, 'birthdate' => null]);

        expect(User::adultWithoutOwnAddress()->pluck('id')->all())->toBe([$unknown->id]);
    });

    it('is how the members list narrows down to them', function (): void {
        $adult = User::factory()->create([
            'email' => null,
            'birthdate' => now()->subYears(30),
            'last_name' => 'Sansadresse',
        ]);

        User::factory()->create([
            'email' => 'reachable@example.com',
            'birthdate' => now()->subYears(30),
            'last_name' => 'Joignable',
        ]);

        Livewire::actingAs(User::factory()->withRole(Role::MEMBERS)->create())
            ->test('pages::club-admin.users.index')
            ->set('adultWithoutAddress', true)
            ->assertSee($adult->last_name)
            ->assertDontSee('Joignable');
    });
});

/*
 * An invitation hands over a login. It goes to the member's own address and
 * never to a guardian's, which would set a password on somebody else's account
 * — so a member without one cannot be offered it, and the screen has to say why
 * rather than let the click fail.
 */
describe('offering to invite a member', function (): void {

    it('does not offer it to a member with no address of their own', function (): void {
        $managed = User::factory()->unverified()->create(['email' => null, 'birthdate' => now()->subYears(12)]);

        Livewire::actingAs(User::factory()->withRole(Role::MEMBERS)->create())
            ->test('pages::club-admin.users.index')
            ->assertDontSee("sendInvitation({$managed->id})", escape: false)
            ->assertSee(__('This member has no address of their own yet, so they cannot be invited.'));
    });

    it('offers it to a member who has one', function (): void {
        $member = User::factory()->unverified()->create(['email' => 'member@example.com']);

        Livewire::actingAs(User::factory()->withRole(Role::MEMBERS)->create())
            ->test('pages::club-admin.users.index')
            ->assertSee("sendInvitation({$member->id})", escape: false);
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
