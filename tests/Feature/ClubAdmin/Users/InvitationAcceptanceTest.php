<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

function invitedUser(): User
{
    return User::factory()->unverified()->create([
        'password' => '',
        'last_invited_at' => now(),
    ]);
}

function signedInvitationUrl(User $user): string
{
    return URL::temporarySignedRoute(
        'invitation.accept',
        now()->addDays(User::INVITATION_LINK_VALIDITY_DAYS),
        ['user' => $user->id]
    );
}

test('unsigned post is rejected and password stays unchanged', function (): void {
    $user = invitedUser();
    $originalPassword = $user->fresh()->password;

    $response = $this->post(route('invitation.store', ['user' => $user->id]), [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertForbidden();
    expect($user->fresh()->password)->toBe($originalPassword);
    $this->assertGuest();
});

test('signed get shows the invitation form for an invited user', function (): void {
    $user = invitedUser();

    $this->get(signedInvitationUrl($user))
        ->assertSuccessful()
        ->assertSee($user->email);
});

test('signed post sets the password, verifies the email and logs the user in', function (): void {
    $user = invitedUser();

    $response = $this->post(signedInvitationUrl($user), [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('admin.user.onboarding'));

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('new-password-123', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

test('signed get for an already activated account redirects to login', function (): void {
    $user = User::factory()->create();

    $this->get(signedInvitationUrl($user))
        ->assertRedirect(route('login'));
});

test('signed post for an already activated account redirects to login without touching the password', function (): void {
    $user = User::factory()->create();
    $originalPassword = $user->password;

    $response = $this->post(signedInvitationUrl($user), [
        'password' => 'hijacked-password-123',
        'password_confirmation' => 'hijacked-password-123',
    ]);

    $response->assertRedirect(route('login'));
    expect($user->fresh()->password)->toBe($originalPassword);
    $this->assertGuest();
});

test('a 7-character password is rejected', function (): void {
    $user = invitedUser();

    $this->post(signedInvitationUrl($user), [
        'password' => 'short12',
        'password_confirmation' => 'short12',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});

test('an 8-character password is accepted', function (): void {
    $user = invitedUser();

    $this->post(signedInvitationUrl($user), [
        'password' => 'longer12',
        'password_confirmation' => 'longer12',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('invitation link is still valid on day 6', function (): void {
    $user = invitedUser();
    $url = signedInvitationUrl($user);

    $this->travel(6)->days();

    $this->get($url)->assertSuccessful();
});

test('expired signature shows the dedicated expired page with a resend button', function (): void {
    $user = invitedUser();
    $originalPassword = $user->fresh()->password;
    $url = signedInvitationUrl($user);

    $this->travel(8)->days();

    $this->get($url)
        ->assertForbidden()
        ->assertSee(__('This invitation link has expired.'))
        ->assertSee(__('Receive a new link'));
    $this->post($url, [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertForbidden();

    expect($user->fresh()->password)->toBe($originalPassword);
});

test('expired link for an activated account shows a neutral message without resend button', function (): void {
    $user = User::factory()->create();
    $url = signedInvitationUrl($user);

    $this->travel(8)->days();

    $this->get($url)
        ->assertForbidden()
        ->assertSee(__('Please contact the club to receive a new invitation.'))
        ->assertDontSee(__('Receive a new link'));
});

test('resend sends a new invitation to a pending user', function (): void {
    Mail::fake();

    $user = invitedUser();
    $user->update(['last_invited_at' => now()->subDays(10)]);

    $response = $this->post(route('invitation.resend', ['user' => $user->id]));

    $response->assertRedirect(route('login'))
        ->assertSessionHas('status', __('If your account is awaiting activation, a new link has just been sent.'));
    Mail::assertQueued(InviteNewUserMail::class, fn (InviteNewUserMail $mail): bool => $mail->hasTo($user->email));
    expect($user->fresh()->last_invited_at->isSameDay(now()))->toBeTrue();
});

test('resend for an activated account sends nothing but returns the same response', function (): void {
    Mail::fake();

    $user = User::factory()->create();

    $this->post(route('invitation.resend', ['user' => $user->id]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('If your account is awaiting activation, a new link has just been sent.'));

    Mail::assertNothingQueued();
});

test('resend for an unknown user returns the same response', function (): void {
    Mail::fake();

    $this->post(route('invitation.resend', ['user' => 999999]))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', __('If your account is awaiting activation, a new link has just been sent.'));

    Mail::assertNothingQueued();
});

test('invitation mail renders the member name, login and signed link', function (): void {
    $user = invitedUser();
    $link = signedInvitationUrl($user);

    $mail = new InviteNewUserMail($user, $link);
    $html = $mail->render();

    expect($html)
        ->toContain(e($user->first_name . ' ' . $user->last_name))
        ->toContain($user->email)
        ->toContain(e($link))
        ->toContain(__('Finalise my registration'));

    expect($mail->envelope()->subject)
        ->toBe(__('Welcome to :app – Finalize your registration', ['app' => config('app.name')]));
});

test('resend is throttled to 3 attempts per hour', function (): void {
    Mail::fake();

    $user = invitedUser();

    foreach (range(1, 3) as $attempt) {
        $this->post(route('invitation.resend', ['user' => $user->id]))->assertRedirect(route('login'));
    }

    $this->post(route('invitation.resend', ['user' => $user->id]))->assertTooManyRequests();
});
