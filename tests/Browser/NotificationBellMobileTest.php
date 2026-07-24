<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Support\Str;

/*
| The original bug: pages whose content overflowed horizontally (e.g. the meeting
| show page action buttons) widened Firefox mobile's layout viewport, so the
| position:fixed bottom sheet spanned that wider viewport and its right side
| (close button) was cropped. Chromium does not widen the layout viewport, which
| is why sheet-only tests never caught it — the meeting-page test below asserts
| the root cause directly: no horizontal document overflow.
*/

it('does not overflow horizontally on the meeting show page so the sheet cannot be cropped', function (): void {
    $admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $meeting = Meeting::factory()->confirmed()->create(['title' => 'Perspiciatis tempor']);

    $admin->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['icon' => 'o-bell', 'title' => 'Titre', 'body' => 'Corps', 'url' => '#'],
    ]);

    $this->actingAs($admin);

    visit(route('admin.meetings.show', $meeting))
        ->resize(444, 921)
        ->assertScript(
            'document.documentElement.scrollWidth <= window.innerWidth',
            true
        )
        ->click('label[aria-label="Notifications"]')
        ->assertVisible('label[aria-label="Fermer"]')
        ->assertScript(
            "(() => { const r = document.querySelector('label[aria-label=\"Fermer\"]').getBoundingClientRect(); return r.width > 0 && r.right <= window.innerWidth; })()",
            true
        );
});

it('opens the bottom sheet without JavaScript errors and keeps it within the mobile viewport width', function (): void {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => [
            'icon' => 'o-bell',
            'title' => 'Réunion du comité reportée',
            'body' => 'La réunion est reportée.',
            'url' => '#',
        ],
    ]);

    $this->actingAs($user);

    visit(route('notifications.index'))
        ->resize(375, 812)
        ->assertNoJavaScriptErrors()
        ->click('label[aria-label="Notifications"]')
        ->assertVisible('label[aria-label="Fermer"]')
        ->assertScript(
            "(() => { const r = document.querySelector('label[aria-label=\"Fermer\"]').getBoundingClientRect(); return r.width > 0 && r.right <= window.innerWidth && r.left >= 0; })()",
            true
        );
});

it('keeps the close button visible on a narrow phone screen', function (): void {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['icon' => 'o-bell', 'title' => 'Titre', 'body' => 'Corps', 'url' => '#'],
    ]);

    $this->actingAs($user);

    visit(route('notifications.index'))
        ->resize(320, 690)
        ->click('label[aria-label="Notifications"]')
        ->assertVisible('label[aria-label="Fermer"]')
        ->assertScript(
            "(() => { const r = document.querySelector('label[aria-label=\"Fermer\"]').getBoundingClientRect(); return r.width > 0 && r.right <= window.innerWidth && r.left >= 0; })()",
            true
        );
});

it('keeps both header buttons visible on a Galaxy S25 sized screen with larger system font scaling', function (): void {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['icon' => 'o-bell', 'title' => 'Titre', 'body' => 'Corps', 'url' => '#'],
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['icon' => 'o-bell', 'title' => 'Titre 2', 'body' => 'Corps 2', 'url' => '#'],
    ]);

    $this->actingAs($user);

    visit(route('notifications.index'))
        ->resize(412, 915)
        ->assertScript(
            "(() => { document.documentElement.style.fontSize = '140%'; return true; })()",
            true
        )
        ->click('label[aria-label="Notifications"]')
        ->assertVisible('label[aria-label="Fermer"]')
        ->assertScript(
            "(() => { const close = document.querySelector('label[aria-label=\"Fermer\"]').getBoundingClientRect(); const markAll = document.querySelector('button[aria-label=\"Tout marquer comme lu\"]').getBoundingClientRect(); return close.width > 0 && markAll.width > 0 && close.right <= window.innerWidth && markAll.right <= window.innerWidth; })()",
            true
        );
});

it('keeps both header buttons visible on a very narrow screen with doubled system font scaling', function (): void {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['icon' => 'o-bell', 'title' => 'Titre', 'body' => 'Corps', 'url' => '#'],
    ]);

    $this->actingAs($user);

    visit(route('notifications.index'))
        ->resize(280, 653)
        ->assertScript(
            "(() => { document.documentElement.style.fontSize = '200%'; return true; })()",
            true
        )
        ->click('label[aria-label="Notifications"]')
        ->assertVisible('button[aria-label="Tout marquer comme lu"]')
        ->assertVisible('label[aria-label="Fermer"]')
        ->assertScript(
            "(() => { const close = document.querySelector('label[aria-label=\"Fermer\"]').getBoundingClientRect(); const markAll = document.querySelector('button[aria-label=\"Tout marquer comme lu\"]').getBoundingClientRect(); return close.width > 0 && markAll.width > 0 && close.right <= window.innerWidth && markAll.right <= window.innerWidth; })()",
            true
        );
});

it('closes the sheet when a notification without a destination is tapped', function (): void {
    $user = User::factory()->create();

    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['icon' => 'o-bell', 'title' => 'Titre', 'body' => 'Corps', 'url' => '#'],
    ]);

    $this->actingAs($user);

    visit(route('notifications.index'))
        ->resize(390, 844)
        ->click('label[aria-label="Notifications"]')
        ->assertVisible('label[aria-label="Fermer"]')
        ->click('[dusk="notification-' . $notification->id . '"]')
        ->assertScript(
            "document.getElementById('notification-panel-toggle').checked",
            false
        )
        // The unread badge disappearing confirms the wire:click round-trip
        // completed even though the sheet was closed client-side on tap.
        ->assertScript(
            "document.querySelector('label[aria-label=\"Notifications\"] .bg-error') === null",
            true
        );

    expect($user->notifications()->first()->read_at)->not->toBeNull();
});

it('closes the sheet when the close button is clicked', function (): void {
    $user = User::factory()->create();

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'data' => ['icon' => 'o-bell', 'title' => 'Titre', 'body' => 'Corps', 'url' => '#'],
    ]);

    $this->actingAs($user);

    visit(route('notifications.index'))
        ->resize(412, 915)
        ->click('label[aria-label="Notifications"]')
        ->assertVisible('label[aria-label="Fermer"]')
        ->click('label[aria-label="Fermer"]')
        ->assertScript(
            "document.getElementById('notification-panel-toggle').checked",
            false
        );
});
