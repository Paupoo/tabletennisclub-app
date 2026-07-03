<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Hash;
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

    $response->assertRedirect(route('dashboard'));

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

test('invitation link is still valid on day 6', function (): void {
    $user = invitedUser();
    $url = signedInvitationUrl($user);

    $this->travel(6)->days();

    $this->get($url)->assertSuccessful();
});

test('expired signature is rejected', function (): void {
    $user = invitedUser();
    $originalPassword = $user->fresh()->password;
    $url = signedInvitationUrl($user);

    $this->travel(8)->days();

    $this->get($url)->assertForbidden();
    $this->post($url, [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertForbidden();

    expect($user->fresh()->password)->toBe($originalPassword);
});
