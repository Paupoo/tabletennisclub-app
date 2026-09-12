<?php

declare(strict_types=1);

use App\Actions\User\SendGuardianInvitationAction;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendGuardianInvitationJob;
use App\Mail\InviteGuardianMail;
use App\Mail\InviteNewUserMail;
use App\Support\AccountProxy;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'invitations');

/*
 * A member with no address of their own cannot be handed a login: the link would
 * land in a guardian's mailbox and set a password on somebody else's account. So
 * the club invites the guardian instead — they create an account of their own and
 * gain the proxy over the member, which is everything the invitation would have
 * opened.
 */

/** A member the club records but who has no mailbox of their own. */
function ward(): User
{
    $ward = User::factory()->create(['email' => null, 'birthdate' => now()->subYears(12)]);

    // `email_verified_at` is not fillable, and a managed account never verified
    // an address it does not have.
    $ward->forceFill(['email_verified_at' => null])->save();

    return $ward;
}

/** Attach a brand new guardian sheet — no account of their own — to a ward. */
function guardianOf(User $ward, array $attributes = []): Guardian
{
    $guardian = Guardian::factory()->create($attributes);
    $ward->guardians()->attach($guardian);
    $ward->load('guardians.member');

    return $guardian;
}

describe('sending a guardian their invitation', function (): void {

    it('queues it and records when it went out', function (): void {
        Mail::fake();
        $guardian = guardianOf(ward(), ['email' => 'parent@example.com']);

        expect(SendGuardianInvitationAction::handle($guardian))->toBeTrue();

        Mail::assertQueued(InviteGuardianMail::class);
        expect($guardian->fresh()->last_invited_at)->not->toBeNull();
    });

    it('refuses a guardian the club has no address for', function (): void {
        Mail::fake();
        $guardian = guardianOf(ward(), ['email' => null]);

        expect(SendGuardianInvitationAction::handle($guardian))->toBeFalse();
        Mail::assertNothingQueued();
    });

    /*
     * The office types a parent's details without knowing the club already has
     * them on file. `users.email` is unique, so creating a second identity would
     * only surface later — as a failure on the link the club just sent them.
     */
    it('recognises a guardian whose address already belongs to a member', function (): void {
        Mail::fake();
        $member = User::factory()->unverified()->create(['email' => 'known@example.com']);
        $guardian = guardianOf(ward(), ['email' => 'known@example.com']);

        expect(SendGuardianInvitationAction::handle($guardian))->toBeTrue()
            ->and($guardian->fresh()->user_id)->toBe($member->id)
            ->and(User::where('email', 'known@example.com')->count())->toBe(1);

        // The ordinary member invitation takes over: this person has an account.
        Mail::assertQueued(InviteNewUserMail::class);
        Mail::assertNotQueued(InviteGuardianMail::class);
    });

    it('sends nothing to a guardian who has already activated their account', function (): void {
        Mail::fake();
        $member = User::factory()->create(['email' => 'active@example.com']);
        $guardian = guardianOf(ward(), ['email' => 'active@example.com', 'user_id' => $member->id]);

        expect(SendGuardianInvitationAction::handle($guardian))->toBeFalse();
        Mail::assertNothingQueued();
    });

    /*
     * The limiter is the club's outgoing mail as Gmail sees it, and a burst is a
     * burst whether it is addressed to members or to their parents.
     */
    it('shares the members own sending limiter', function (): void {
        $job = new SendGuardianInvitationJob(guardianOf(ward())->id);

        expect($job->middleware())->toHaveCount(1)
            ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
    });
});

describe('where a managed member stands', function (): void {

    it('reads as a guardian to invite when nobody has been written to', function (): void {
        $ward = ward();
        guardianOf($ward, ['email' => 'parent@example.com']);

        expect($ward->guardianshipStatus())->toBe('guardian_to_invite')
            ->and($ward->invitableGuardians())->toHaveCount(1);
    });

    it('reads as a guardian to invite when the guardian is a member nobody ever invited', function (): void {
        $ward = ward();
        $member = User::factory()->unverified()->create(['last_invited_at' => null]);
        guardianOf($ward, ['user_id' => $member->id]);

        expect($ward->guardianshipStatus())->toBe('guardian_to_invite');
    });

    it('reads as invited once the link is out', function (): void {
        Mail::fake();
        $ward = ward();
        SendGuardianInvitationAction::handle(guardianOf($ward, ['email' => 'parent@example.com']));

        expect($ward->fresh()->load('guardians.member')->guardianshipStatus())->toBe('guardian_invited');
    });

    it('reads as managed once a guardian holds an active account', function (): void {
        $ward = ward();
        guardianOf($ward, ['user_id' => User::factory()->create()->id]);

        expect($ward->guardianshipStatus())->toBe('managed')
            ->and($ward->invitableGuardians())->toBeEmpty();
    });

    it('reads as unreachable when no guardian has an address or an account', function (): void {
        $ward = ward();
        guardianOf($ward, ['email' => null]);

        expect($ward->guardianshipStatus())->toBe('guardian_unreachable');
    });

    it('says nothing about a member who has an address of their own', function (): void {
        expect(User::factory()->create(['email' => 'own@example.com'])->guardianshipStatus())->toBeNull();
    });

    /*
     * One rule expressed twice — once per row for the badge, once in SQL for the
     * filter. They are only worth having if they agree.
     */
    it('filters exactly the members the badge names', function (): void {
        Mail::fake();

        $toInvite = ward();
        guardianOf($toInvite, ['email' => 'to-invite@example.com']);

        $invited = ward();
        SendGuardianInvitationAction::handle(guardianOf($invited, ['email' => 'invited@example.com']));

        $managed = ward();
        guardianOf($managed, ['user_id' => User::factory()->create()->id]);

        $unreachable = ward();
        guardianOf($unreachable, ['email' => null]);

        foreach ([
            'guardian_to_invite' => $toInvite,
            'guardian_invited' => $invited,
            'managed' => $managed,
            'guardian_unreachable' => $unreachable,
        ] as $state => $expected) {
            expect(User::withGuardianshipState($state)->pluck('id')->all())
                ->toBe([$expected->id], "state {$state}");
        }
    });

    /*
     * Their own row would sit under "not invited" for good, whatever the office
     * sent: nobody will ever hand a login to an address that does not exist.
     */
    it('keeps managed members out of the members own not-invited filter', function (): void {
        $ward = ward();
        guardianOf($ward, ['email' => 'parent@example.com']);

        expect(User::withInvitationState('not_invited')->pluck('id')->all())->not->toContain($ward->id);
    });
});

describe('accepting a guardian invitation', function (): void {

    /** The signed link the club mails out. */
    function acceptanceLink(Guardian $guardian): string
    {
        return URL::temporarySignedRoute(
            'guardian-invitation.accept',
            now()->addDays(User::INVITATION_LINK_VALIDITY_DAYS),
            ['guardian' => $guardian->id]
        );
    }

    it('refuses a link nobody signed', function (): void {
        get(route('guardian-invitation.accept', ['guardian' => guardianOf(ward())->id]))
            ->assertForbidden();
    });

    it('shows the details on file so the parent can correct them', function (): void {
        $guardian = guardianOf(ward(), ['first_name' => 'Cristina', 'email' => 'parent@example.com']);

        get(acceptanceLink($guardian))
            ->assertOk()
            ->assertSee('Cristina')
            ->assertSee('parent@example.com');
    });

    it('creates the account, links the sheet and hands over the ward seat', function (): void {
        $ward = ward();
        $guardian = guardianOf($ward, ['email' => 'parent@example.com']);

        post(acceptanceLink($guardian), [
            'first_name' => 'Cristina',
            'last_name' => 'Decreton',
            'gender' => 'WOMEN',
            'phone' => '0475123456',
            'password' => 'Sup3r-Secret!',
            'password_confirmation' => 'Sup3r-Secret!',
        ])->assertRedirect(route('dashboard'));

        $parent = User::where('email', 'parent@example.com')->sole();

        expect($parent->first_name)->toBe('Cristina')
            ->and($parent->email_verified_at)->not->toBeNull()
            ->and($guardian->fresh()->user_id)->toBe($parent->id)
            ->and($parent->mayActFor($ward))->toBeTrue();

        // The proxy is already held: the parent came here for that account.
        expect(auth()->id())->toBe($ward->id)
            ->and(AccountProxy::origin()?->id)->toBe($parent->id);
    });

    /*
     * The wizard exists so that a *player's* file is complete. A parent has no
     * licence and no subscription, so it has nothing to validate on them — and
     * it sits between them and the child they followed the link for.
     */
    it('does not park the parent in the onboarding wizard', function (): void {
        $guardian = guardianOf(ward(), ['email' => 'parent@example.com']);

        post(acceptanceLink($guardian), [
            'first_name' => 'Cristina',
            'last_name' => 'Decreton',
            'gender' => 'WOMEN',
            'phone' => '0475123456',
            'password' => 'Sup3r-Secret!',
            'password_confirmation' => 'Sup3r-Secret!',
        ]);

        $parent = User::where('email', 'parent@example.com')->sole();

        expect($parent->birthdate)->toBeNull()
            ->and($parent->isGuardianOnlyAccount())->toBeTrue()
            ->and($parent->hasCompleteProfile())->toBeTrue();

        AccountProxy::stop();
        actingAs($parent)->get(route('dashboard'))->assertOk();
    });

    /*
     * Single-use by construction rather than by a flag: once the sheet carries an
     * account there is nothing left for the link to create.
     */
    it('is spent once the sheet carries an account', function (): void {
        $guardian = guardianOf(ward(), ['email' => 'parent@example.com', 'user_id' => User::factory()->create()->id]);

        get(acceptanceLink($guardian))->assertRedirect(route('login'));
    });

    it('lets the parent pick when they answer for several members', function (): void {
        $guardian = guardianOf(ward(), ['email' => 'parent@example.com']);
        $second = ward();
        $second->guardians()->attach($guardian);

        post(acceptanceLink($guardian), [
            'first_name' => 'Cristina',
            'last_name' => 'Decreton',
            'gender' => 'WOMEN',
            'phone' => '0475123456',
            'password' => 'Sup3r-Secret!',
            'password_confirmation' => 'Sup3r-Secret!',
        ])->assertRedirect(route('dashboard'));

        $parent = User::where('email', 'parent@example.com')->sole();

        expect(auth()->id())->toBe($parent->id)
            ->and(AccountProxy::isActing())->toBeFalse()
            ->and($parent->managedAccounts())->toHaveCount(2);
    });
});

describe('inviting guardians from the members list', function (): void {

    beforeEach(function (): void {
        actingAs(User::factory()->withRole(Role::MEMBERS)->create());
    });

    it('offers the guardian on the row of a member who has no address', function (): void {
        $ward = ward();
        $guardian = guardianOf($ward, ['first_name' => 'Cristina', 'last_name' => 'Decreton']);

        Livewire::test('pages::club-admin.users.index')
            ->assertDontSee("sendInvitation({$ward->id})", escape: false)
            ->assertSee("sendGuardianInvitation({$ward->id})", escape: false)
            ->assertSee($guardian->full_name);
    });

    it('sends to every guardian of the row, separated parents included', function (): void {
        Mail::fake();
        $ward = ward();
        guardianOf($ward, ['email' => 'mother@example.com']);
        guardianOf($ward, ['email' => 'father@example.com']);

        Livewire::test('pages::club-admin.users.index')
            ->call('sendGuardianInvitation', $ward->id);

        Mail::assertQueued(InviteGuardianMail::class, 2);
    });

    it('sends nothing when every guardian is already set up', function (): void {
        Mail::fake();
        $ward = ward();
        guardianOf($ward, ['user_id' => User::factory()->create()->id]);

        Livewire::test('pages::club-admin.users.index')
            ->call('sendGuardianInvitation', $ward->id);

        Mail::assertNothingQueued();
    });

    /*
     * The row has to say who answers for the member, not merely that nobody can
     * be invited: naming them is what turns a dead end into an instruction.
     */
    it('names the guardian on the row instead of calling the member uninvitable', function (): void {
        $ward = ward();
        $member = User::factory()->create(['first_name' => 'Cristina', 'last_name' => 'Decreton']);
        guardianOf($ward, ['user_id' => $member->id, 'first_name' => 'Cristina', 'last_name' => 'Decreton']);

        Livewire::test('pages::club-admin.users.index')
            ->assertDontSee("sendGuardianInvitation({$ward->id})", escape: false)
            ->assertSee(__(':names answers for this member and manages their account.', ['names' => 'Cristina Decreton']));
    });

    it('is closed to a member who may not write to the club', function (): void {
        actingAs(User::factory()->create());
        $ward = ward();
        guardianOf($ward);

        Livewire::test('pages::club-admin.users.index')
            ->call('sendGuardianInvitation', $ward->id)
            ->assertForbidden();
    });
});

describe('inviting guardians in bulk', function (): void {

    beforeEach(function (): void {
        actingAs(User::factory()->withRole(Role::MEMBERS)->create());
    });

    /*
     * One click can now write to people who are not in the selection, so it is
     * confirmed with its count rather than simply fired.
     */
    it('asks before writing to a parent, then queues one job per guardian', function (): void {
        Bus::fake();
        $ward = ward();
        guardianOf($ward, ['email' => 'parent@example.com']);

        $component = Livewire::test('pages::club-admin.users.index')
            ->set('selected', [(string) $ward->id])
            ->call('bulkInvite')
            ->assertSet('confirmReinviteModal', true)
            ->assertSet('guardiansToInvite', 1);

        Bus::assertNothingBatched();

        $component->call('confirmBulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof SendGuardianInvitationJob);
    });

    /*
     * A parent of two selected children is written to once: two near-identical
     * emails read as a fault of the club.
     */
    it('writes once to a parent answering for two selected members', function (): void {
        Bus::fake();
        $guardian = Guardian::factory()->create(['email' => 'parent@example.com']);
        $first = ward();
        $second = ward();
        $first->guardians()->attach($guardian);
        $second->guardians()->attach($guardian);

        Livewire::test('pages::club-admin.users.index')
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->call('bulkInvite')
            ->assertSet('guardiansToInvite', 1)
            ->call('confirmBulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
    });

    it('mixes members and guardians in the same batch', function (): void {
        Bus::fake();
        $member = User::factory()->unverified()->create(['email' => 'member@example.com', 'last_invited_at' => null]);
        $ward = ward();
        guardianOf($ward, ['email' => 'parent@example.com']);

        Livewire::test('pages::club-admin.users.index')
            ->set('selected', [(string) $member->id, (string) $ward->id])
            ->call('bulkInvite')
            ->call('confirmBulkInvite');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 2);
    });
});

/*
 * `hasCompleteProfile()` and `withIncompleteProfile()` are one rule expressed
 * twice, so the exemption has to land on both.
 */
it('agrees in SQL that a guardian-only account has nothing to complete', function (): void {
    $ward = ward();
    $parent = User::factory()->create(['birthdate' => null, 'street' => null]);
    guardianOf($ward, ['user_id' => $parent->id]);

    expect($parent->hasCompleteProfile())->toBeTrue()
        ->and(User::withIncompleteProfile()->pluck('id')->all())->not->toContain($parent->id);
});
