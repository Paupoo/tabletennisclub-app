<?php

declare(strict_types=1);

use App\Actions\User\AnonymizeUserAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\OnboardFromContactAction;
use App\Actions\User\RestoreUserAction;
use App\Actions\User\SendInvitationAction;
use App\Actions\User\SoftDeleteUserAction;
use App\Actions\User\UpdateUserAction;
use App\Data\User\CreateUserData;
use App\Data\User\UpdateUserData;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Domains\Shared\Enums\Gender;
use App\Mail\InviteNewUserMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

// ── CreateUserAction ─────────────────────────────────────────────────────────

describe('CreateUserAction', function (): void {
    it('creates a user from DTO', function (): void {
        $actor = $this->createFakeAdmin();

        Mail::fake();

        $data = new CreateUserData(
            first_name: 'Jean',
            last_name: 'Dupont',
            email: 'jean.dupont@example.com',
            gender: Gender::MEN,
        );

        $user = CreateUserAction::handle($data, $actor);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->first_name)->toBe('Jean')
            ->and($user->last_name)->toBe('Dupont')
            ->and($user->email)->toBe('jean.dupont@example.com')
            ->and($user->updated_by)->toBe($actor->id);
    });

    it('sends invitation email on creation', function (): void {
        $actor = $this->createFakeAdmin();

        Mail::fake();

        $data = new CreateUserData(
            first_name: 'Marie',
            last_name: 'Martin',
            email: 'marie.martin@example.com',
            gender: Gender::WOMEN,
        );

        CreateUserAction::handle($data, $actor);

        Mail::assertQueued(InviteNewUserMail::class, fn ($mail) => $mail->hasTo('marie.martin@example.com'));
    });

    it('sets last_invited_at on creation', function (): void {
        $actor = $this->createFakeAdmin();

        Mail::fake();

        $data = new CreateUserData(
            first_name: 'Pierre',
            last_name: 'Leclerc',
            email: 'pierre.leclerc@example.com',
            gender: Gender::MEN,
        );

        $user = CreateUserAction::handle($data, $actor);

        expect($user->last_invited_at)->not->toBeNull();
    });
});

// ── UpdateUserAction ─────────────────────────────────────────────────────────

describe('UpdateUserAction', function (): void {
    it('updates fields and records the actor in updated_by', function (): void {
        $actor = $this->createFakeAdmin();
        $user = User::factory()->create(['first_name' => 'Old', 'last_name' => 'Name']);

        UpdateUserAction::handle(
            $user,
            new UpdateUserData(
                first_name: 'New',
                last_name: 'Name',
                email: $user->email,
                gender: Gender::MEN,
            ),
            $actor,
        );

        expect($user->fresh()->first_name)->toBe('New')
            ->and($user->fresh()->updated_by)->toBe($actor->id);
    });

    it('leaves the password unchanged when none is provided', function (): void {
        $actor = $this->createFakeAdmin();
        $user = User::factory()->create();
        $originalHash = $user->password;

        UpdateUserAction::handle(
            $user,
            new UpdateUserData(
                first_name: $user->first_name,
                last_name: $user->last_name,
                email: $user->email,
                gender: Gender::MEN,
                password: null,
            ),
            $actor,
        );

        expect($user->fresh()->password)->toBe($originalHash);
    });

    it('hashes and updates the password when provided', function (): void {
        $actor = $this->createFakeAdmin();
        $user = User::factory()->create();
        $originalHash = $user->password;

        UpdateUserAction::handle(
            $user,
            new UpdateUserData(
                first_name: $user->first_name,
                last_name: $user->last_name,
                email: $user->email,
                gender: Gender::MEN,
                password: 'Sup3r-Secret!42',
            ),
            $actor,
        );

        expect($user->fresh()->password)->not->toBe($originalHash)
            ->and(Hash::check('Sup3r-Secret!42', $user->fresh()->password))->toBeTrue();
    });

    it('syncs the linked guardians', function (): void {
        $actor = $this->createFakeAdmin();
        $user = User::factory()->create();
        $guardian = Guardian::factory()->create();

        UpdateUserAction::handle(
            $user,
            new UpdateUserData(
                first_name: $user->first_name,
                last_name: $user->last_name,
                email: $user->email,
                gender: Gender::MEN,
                guardianIds: [$guardian->id],
            ),
            $actor,
        );

        expect($user->fresh()->guardians)->toHaveCount(1)
            ->and($user->fresh()->guardians->first()->id)->toBe($guardian->id);
    });

    it('requires re-verification and notifies when the email changes', function (): void {
        Notification::fake();

        $actor = $this->createFakeAdmin();
        $user = User::factory()->create(['email_verified_at' => now()]);

        UpdateUserAction::handle(
            $user,
            new UpdateUserData(
                first_name: $user->first_name,
                last_name: $user->last_name,
                email: 'changed.address@example.com',
                gender: Gender::MEN,
            ),
            $actor,
        );

        expect($user->fresh()->email_verified_at)->toBeNull();
        Notification::assertSentTo($user->fresh(), VerifyEmail::class);
    });

    it('keeps the verification status when the email is unchanged', function (): void {
        Notification::fake();

        $actor = $this->createFakeAdmin();
        $user = User::factory()->create(['email_verified_at' => now()]);

        UpdateUserAction::handle(
            $user,
            new UpdateUserData(
                first_name: 'Renamed',
                last_name: $user->last_name,
                email: $user->email,
                gender: Gender::MEN,
            ),
            $actor,
        );

        expect($user->fresh()->email_verified_at)->not->toBeNull();
        Notification::assertNothingSent();
    });
});

// ── SoftDeleteUserAction ─────────────────────────────────────────────────────

describe('SoftDeleteUserAction', function (): void {
    it('soft deletes a user', function (): void {
        $user = User::factory()->create();

        SoftDeleteUserAction::handle($user);

        expect(User::find($user->id))->toBeNull()
            ->and(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();
    });
});

// ── RestoreUserAction ─────────────────────────────────────────────────────────

describe('RestoreUserAction', function (): void {
    it('restores a soft deleted user', function (): void {
        $user = User::factory()->create();
        $user->delete();

        RestoreUserAction::handle($user);

        expect(User::find($user->id))->not->toBeNull()
            ->and(User::find($user->id)->deleted_at)->toBeNull();
    });
});

// ── AnonymizeUserAction ────────────────────────────────────────────────────────

describe('AnonymizeUserAction', function (): void {
    it('nulls all PII fields', function (): void {
        $user = User::factory()->create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'phone_number' => '0479000000',
            'street' => 'Rue de la Paix 1',
            'city_code' => '1340',
            'city_name' => 'Ottignies',
            'iban' => 'BE68539007547034',
        ]);

        AnonymizeUserAction::handle($user);

        $user->refresh();

        expect($user->first_name)->toBe('Anonymized')
            ->and($user->last_name)->toBe('User')
            ->and($user->email)->toContain('@anonymous.local')
            ->and($user->phone_number)->toBeNull()
            ->and($user->street)->toBeNull()
            ->and($user->city_code)->toBeNull()
            ->and($user->city_name)->toBeNull()
            ->and($user->iban)->toBeNull()
            ->and($user->deleted_at)->not->toBeNull();
    });

    it('sets anonymized email with user id', function (): void {
        $user = User::factory()->create();

        AnonymizeUserAction::handle($user);

        expect($user->fresh()->email)->toBe("deleted-{$user->id}@anonymous.local");
    });
});

// ── SendInvitationAction ───────────────────────────────────────────────────────

describe('SendInvitationAction', function (): void {
    it('queues invitation email to user', function (): void {
        Mail::fake();

        $user = User::factory()->create();

        SendInvitationAction::handle($user);

        Mail::assertQueued(InviteNewUserMail::class, fn ($mail) => $mail->hasTo($user->email));
    });

    it('updates last_invited_at', function (): void {
        Mail::fake();

        $user = User::factory()->create(['last_invited_at' => null]);

        SendInvitationAction::handle($user);

        expect($user->fresh()->last_invited_at)->not->toBeNull();
    });
});

// ── OnboardFromContactAction ───────────────────────────────────────────────────

describe('OnboardFromContactAction', function (): void {
    it('creates user pre-filled from contact data', function (): void {
        Mail::fake();

        $actor = $this->createFakeAdmin();
        $contact = Contact::factory()->create([
            'first_name' => 'Sophie',
            'last_name' => 'Bernard',
            'email' => 'sophie.bernard@example.com',
            'interest' => ContactReasonEnum::JOIN_US,
        ]);

        $user = OnboardFromContactAction::handle($contact, $actor);

        expect($user->first_name)->toBe('Sophie')
            ->and($user->last_name)->toBe('Bernard')
            ->and($user->email)->toBe('sophie.bernard@example.com');
    });

    it('marks contact as processed and links it to the created user', function (): void {
        Mail::fake();

        $actor = $this->createFakeAdmin();
        $contact = Contact::factory()->create(['interest' => ContactReasonEnum::JOIN_US]);

        $user = OnboardFromContactAction::handle($contact, $actor);

        expect($contact->fresh()->status)->toBe('processed')
            ->and($contact->fresh()->user_id)->toBe($user->id);
    });

    it('sends invitation email to new user', function (): void {
        Mail::fake();

        $actor = $this->createFakeAdmin();
        $contact = Contact::factory()->create([
            'email' => 'test.onboard@example.com',
            'interest' => ContactReasonEnum::JOIN_US,
        ]);

        OnboardFromContactAction::handle($contact, $actor);

        Mail::assertQueued(InviteNewUserMail::class, fn ($mail) => $mail->hasTo('test.onboard@example.com'));
    });
});
