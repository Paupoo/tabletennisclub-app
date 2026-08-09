<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

/*
|--------------------------------------------------------------------------
| Session flash → Mary toast bridge (admin layout)
|--------------------------------------------------------------------------
|
| Controllers ending in redirect()->with('success'|'error', …) used to flash
| messages nobody displayed: the admin layout only listens to Livewire toast
| events. The bridge in layouts/app.blade.php turns those flashes into the
| same Mary toasts.
|
*/

test('a success flash is rendered as a mary toast on the next admin page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['success' => 'Flash bridge success message'])
        ->get(route('admin.user.profile', $user))
        ->assertSuccessful()
        ->assertSee('Flash bridge success message')
        ->assertSee('alert-success');
});

test('an error flash is rendered as a mary toast on the next admin page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['error' => 'Flash bridge error message'])
        ->get(route('admin.user.profile', $user))
        ->assertSuccessful()
        ->assertSee('Flash bridge error message')
        ->assertSee('alert-error');
});

test('no bridge markup is rendered without a flash message', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.user.profile', $user))
        ->assertSuccessful()
        ->assertDontSee('Flash bridge');
});
