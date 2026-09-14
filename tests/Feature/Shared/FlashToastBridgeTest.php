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
| It first listened to two levels only, so ->with('warning', …) still flashed
| into the void — and did so on the one screen where it mattered most: the bar's
| cash sheet reported a failed send to the treasurer as a warning, which meant
| it reported it to nobody. A barman closed the till believing the figures had
| gone out. Whether a message is seen cannot depend on the word its caller
| picked, so the bridge now resolves all four levels.
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

test('every flash level reaches the toast area, not just success and error', function (string $level, string $css): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([$level => 'Flash bridge ' . $level . ' message'])
        ->get(route('admin.user.profile', $user))
        ->assertSuccessful()
        ->assertSee('Flash bridge ' . $level . ' message')
        ->assertSee($css);
})->with([
    'success' => ['success', 'alert-success'],
    'error' => ['error', 'alert-error'],
    'warning' => ['warning', 'alert-warning'],
    'info' => ['info', 'alert-info'],
]);
