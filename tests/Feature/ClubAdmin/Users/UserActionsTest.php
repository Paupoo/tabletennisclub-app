<?php

declare(strict_types=1);

use App\Actions\User\AnonymizeUserAction;
use App\Actions\User\CreateUserAction;
use App\Actions\User\OnboardFromContactAction;
use App\Actions\User\RestoreUserAction;
use App\Actions\User\SendInvitationAction;
use App\Actions\User\SoftDeleteUserAction;
use App\Actions\User\ToggleActiveAction;
use App\Data\User\CreateUserData;
use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Domains\Shared\Enums\Gender;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;
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

// ── ToggleActiveAction ─────────────────────────────────────────────────────────

describe('ToggleActiveAction', function (): void {
    it('activates an inactive user', function (): void {
        $actor = $this->createFakeAdmin();
        $user = User::factory()->create(['is_active' => false]);

        ToggleActiveAction::handle($user, true, $actor);

        expect($user->fresh()->is_active)->toBeTrue()
            ->and($user->fresh()->updated_by)->toBe($actor->id);
    });

    it('deactivates an active user', function (): void {
        $actor = $this->createFakeAdmin();
        $user = User::factory()->create(['is_active' => true]);

        ToggleActiveAction::handle($user, false, $actor);

        expect($user->fresh()->is_active)->toBeFalse();
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

    it('marks contact as processed', function (): void {
        Mail::fake();

        $actor = $this->createFakeAdmin();
        $contact = Contact::factory()->create(['interest' => ContactReasonEnum::JOIN_US]);

        OnboardFromContactAction::handle($contact, $actor);

        expect($contact->fresh()->status)->toBe('processed');
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
