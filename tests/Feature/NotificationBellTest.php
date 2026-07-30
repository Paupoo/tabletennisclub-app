<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Livewire\Admin\NotificationBell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createNotificationFor(User $user, ?string $url = '#'): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $id,
        'type' => 'App\\Notifications\\TestNotification',
        'data' => [
            'icon' => 'o-bell',
            'title' => 'Titre',
            'body' => 'Corps',
            'url' => $url,
        ],
    ]);

    return $id;
}

it('marks a single notification as read without redirecting when url is #', function (): void {
    $user = User::factory()->create();
    $id = createNotificationFor($user, '#');

    Livewire::actingAs($user)
        ->test(NotificationBell::class)
        ->call('markAsRead', $id)
        ->assertNoRedirect();

    expect($user->notifications()->find($id)->read_at)->not->toBeNull();
});

it('redirects to the notification url when marking it as read', function (): void {
    $user = User::factory()->create();
    $id = createNotificationFor($user, '/admin/dashboard');

    Livewire::actingAs($user)
        ->test(NotificationBell::class)
        ->call('markAsRead', $id)
        ->assertRedirect('/admin/dashboard');

    expect($user->notifications()->find($id)->read_at)->not->toBeNull();
});

it('marks all unread notifications as read', function (): void {
    $user = User::factory()->create();
    $first = createNotificationFor($user);
    $second = createNotificationFor($user);

    Livewire::actingAs($user)
        ->test(NotificationBell::class)
        ->call('markAllAsRead')
        ->assertDispatched('notifications-updated');

    expect($user->notifications()->find($first)->read_at)->not->toBeNull()
        ->and($user->notifications()->find($second)->read_at)->not->toBeNull();
});
