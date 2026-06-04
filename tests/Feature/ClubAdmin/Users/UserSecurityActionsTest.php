<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\InviteNewUserMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users');

const SECURITY_FORM_COMPONENT = 'pages::club-admin.users.form';
const PROFILE_COMPONENT = 'pages::club-admin.users.user-space.profile';

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'is_coach' => false]);
    actingAs($this->admin);
});

describe('admin form security actions', function () {
    it('resends an invitation and stamps last_invited_at', function () {
        Mail::fake();
        $user = User::factory()->create(['last_invited_at' => null, 'is_coach' => false]);

        Livewire::test(SECURITY_FORM_COMPONENT, ['user' => $user])
            ->call('resendInvitation');

        Mail::assertQueued(InviteNewUserMail::class, fn ($mail) => $mail->hasTo($user->email));
        expect($user->fresh()->last_invited_at)->not->toBeNull();
    });

    it('sends a password reset link to the member', function () {
        Notification::fake();
        $user = User::factory()->create(['is_coach' => false]);

        Livewire::test(SECURITY_FORM_COMPONENT, ['user' => $user])
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('self-service erasure request', function () {
    it('records the erasure request timestamp', function () {
        $user = User::factory()->create(['gdpr_erasure_requested_at' => null]);

        Livewire::actingAs($user)
            ->test(PROFILE_COMPONENT, ['user' => $user])
            ->call('requestErasure');

        expect($user->fresh()->gdpr_erasure_requested_at)->not->toBeNull();
    });
});
