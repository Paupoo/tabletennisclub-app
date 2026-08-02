<?php

declare(strict_types=1);

use App\Actions\User\AnonymizeUserAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubAdmin\Users\Notifications\GdprErasureRequestedNotification;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Mail\InviteNewUserMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users');

const SECURITY_FORM_COMPONENT = 'pages::club-admin.users.form';
const PROFILE_COMPONENT = 'pages::club-admin.users.user-space.profile';
// Erasure lives in account settings, not on the profile page.
const SECURITY_SETTINGS_COMPONENT = 'pages::club-admin.users.user-space.settings';

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    actingAs($this->admin);
});

describe('admin form security actions', function (): void {
    it('resends an invitation and stamps last_invited_at', function (): void {
        Mail::fake();
        $user = User::factory()->create(['last_invited_at' => null]);

        Livewire::test(SECURITY_FORM_COMPONENT, ['user' => $user])
            ->call('resendInvitation');

        Mail::assertQueued(InviteNewUserMail::class, fn ($mail) => $mail->hasTo($user->email));
        expect($user->fresh()->last_invited_at)->not->toBeNull();
    });

    it('sends a password reset link to the member', function (): void {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test(SECURITY_FORM_COMPONENT, ['user' => $user])
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('self-service erasure request', function (): void {
    it('records the erasure request timestamp', function (): void {
        $user = User::factory()->create(['gdpr_erasure_requested_at' => null]);

        Livewire::actingAs($user)
            ->test(SECURITY_SETTINGS_COMPONENT, ['user' => $user])
            ->call('requestErasure');

        expect($user->fresh()->gdpr_erasure_requested_at)->not->toBeNull();
    });

    it('notifies admins and the secretary but not regular members', function (): void {
        Notification::fake();

        $secretary = User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::SECRETARY]);
        $lambda = User::factory()->create();
        $member = User::factory()->create(['gdpr_erasure_requested_at' => null]);

        Livewire::actingAs($member)
            ->test(SECURITY_SETTINGS_COMPONENT, ['user' => $member])
            ->call('requestErasure');

        Notification::assertSentTo([$this->admin, $secretary], GdprErasureRequestedNotification::class);
        Notification::assertNotSentTo([$lambda, $member], GdprErasureRequestedNotification::class);
    });

    it('is idempotent and keeps the original request date', function (): void {
        Notification::fake();

        $originalDate = Carbon::parse('2026-06-01 10:00:00');
        $member = User::factory()->create(['gdpr_erasure_requested_at' => $originalDate]);

        Livewire::actingAs($member)
            ->test(SECURITY_SETTINGS_COMPONENT, ['user' => $member])
            ->call('requestErasure');

        Notification::assertNothingSent();
        expect($member->fresh()->gdpr_erasure_requested_at->equalTo($originalDate))->toBeTrue();
    });

    it('renders the erasure mail with the member name', function (): void {
        $member = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);

        $mail = (new GdprErasureRequestedNotification($member))->toMail($this->admin);

        expect((string) $mail->render())->toContain('Jean Dupont');
    });

    it('detects a subscription awaiting payment', function (): void {
        $member = User::factory()->create();
        expect($member->hasPendingPayments())->toBeFalse();

        Subscription::factory()->create(['user_id' => $member->id, 'status' => 'pending']);

        expect($member->fresh()->hasPendingPayments())->toBeTrue();
    });

    it('flags pending payments in the erasure mail', function (): void {
        $member = User::factory()->create();
        Subscription::factory()->create(['user_id' => $member->id, 'status' => 'pending']);

        $mail = (new GdprErasureRequestedNotification($member->fresh()))->toMail($this->admin);

        expect((string) $mail->render())->toContain('⚠️');
    });

    it('drops the in-app erasure notification once the member is anonymized', function (): void {
        $member = User::factory()->create();

        $this->admin->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => GdprErasureRequestedNotification::class,
            'data' => ['member_id' => $member->id, 'member_name' => $member->full_name],
            'read_at' => null,
        ]);

        AnonymizeUserAction::handle($member);

        expect($this->admin->notifications()->count())->toBe(0);
    });
});
