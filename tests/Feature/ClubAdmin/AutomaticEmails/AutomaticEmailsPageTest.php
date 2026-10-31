<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\Role;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

// ── Access control ──────────────────────────────────────────────────────────

test('a regular member cannot open the automatic emails', function (): void {
    $this->actingAs($this->createFakeUser())
        ->get(route('admin.automatic-emails.index'))
        ->assertForbidden();
});

test('a committee member without the supervision delegation cannot open them', function (): void {
    $this->actingAs($this->createFakeCommitteeMember())
        ->get(route('admin.automatic-emails.index'))
        ->assertForbidden();
});

test('the supervision delegation opens them', function (): void {
    $this->actingAs(tap($this->createFakeCommitteeMember(), fn ($u) => $u->assignRole(Role::SUPERVISION->value)))
        ->get(route('admin.automatic-emails.index'))
        ->assertOk()
        ->assertSee(__('Automatic emails'));
});

test('they go away with the supervision', function (): void {
    config(['features.supervision' => false]);

    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.automatic-emails.index'))
        ->assertNotFound();
});

// ── Content ─────────────────────────────────────────────────────────────────

test('it shows the scheduled emails with their cadence, and the others by domain', function (): void {
    $this->travelTo('2026-10-31 10:00');

    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.automatic-emails.index'))
        ->assertOk()
        ->assertSeeInOrder([
            __('Continuously'),
            __('Email'),
            __('Recipients'),
            __('Sending condition'),
            __('Tournament payment reminder'),
            __('In the coming week'),
            __('Tomorrow'),
            __('Lineup reminder'),
            '18h00',
            __('Sent when something happens'),
            __('Tournament invitation'),
        ])
        ->assertSee('Chaque jeudi à 12h00')
        ->assertSee(__('Members can turn it off: « :preference »', ['preference' => __('New tournaments and events')]), escape: false);
});

test('a switched-off domain stays on the page, marked', function (): void {
    config(['features.bar' => false]);

    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.automatic-emails.index'))
        ->assertOk()
        ->assertSee(__('Restocking list'))
        ->assertSee(__('Will not leave while its domain is switched off.'))
        ->assertSee(__('Domain switched off'));
});

test('the supervision menu leads to it', function (): void {
    $this->actingAs($this->createFakeAdmin())
        ->get(route('admin.queue.index'))
        ->assertSee(route('admin.automatic-emails.index'));
});
