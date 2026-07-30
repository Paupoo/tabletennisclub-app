<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
|--------------------------------------------------------------------------
| profile.complete middleware
|--------------------------------------------------------------------------
|
| Members whose profile misses required fields (birthdate, phone, address)
| are locked out of the back office and sent to the onboarding wizard.
| Everything outside admin/coach (public site, auth, signed routes) stays
| reachable.
|
*/

function userWithIncompleteProfile(): User
{
    return User::factory()->create([
        'birthdate' => null,
        'phone_number' => null,
        'street' => null,
        'city_code' => null,
        'city_name' => null,
    ]);
}

test('an incomplete profile is redirected from the dashboard to the wizard', function (): void {
    $this->actingAs(userWithIncompleteProfile())
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.user.onboarding'));
});

test('an incomplete profile cannot deep-link into another admin page', function (): void {
    $user = userWithIncompleteProfile();

    $this->actingAs($user)
        ->get(route('admin.user.profile', $user))
        ->assertRedirect(route('admin.user.onboarding'));
});

test('the wizard itself stays reachable with an incomplete profile', function (): void {
    $this->actingAs(userWithIncompleteProfile())
        ->get(route('admin.user.onboarding'))
        ->assertSuccessful();
});

test('an incomplete profile can still log out', function (): void {
    $this->actingAs(userWithIncompleteProfile())
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

test('an incomplete profile can still browse the public site', function (): void {
    $this->actingAs(userWithIncompleteProfile())
        ->get(route('home'))
        ->assertSuccessful();
});

test('a complete profile reaches the dashboard normally', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSuccessful();
});

test('a guest is still sent to login, not to the wizard', function (): void {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('completing the wizard unlocks the back office', function (): void {
    $user = userWithIncompleteProfile();

    $user->update([
        'birthdate' => '1990-01-01',
        'phone_number' => '0470 00 00 00',
        'street' => 'Rue de la Station 1',
        'city_code' => '1340',
        'city_name' => 'Ottignies',
    ]);

    $this->actingAs($user->fresh())
        ->get(route('dashboard'))
        ->assertSuccessful();
});
