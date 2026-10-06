<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const DUTY_SETTINGS_COMPONENT = 'pages::club-admin.users.user-space.settings';

it('offers the « Who does what » section to a committee member', function (): void {
    $member = User::factory()->create();
    $member->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($member)
        ->test(DUTY_SETTINGS_COMPONENT, ['user' => $member])
        ->assertSee('Sur la page « Qui fait quoi »')
        ->assertSet('dutyShareEmail', true)
        ->assertSet('dutySharePhone', false);
});

it('offers the section to a duty holder outside the committee', function (): void {
    $keeper = User::factory()->create();
    $keeper->assignRole(Role::STORE_KEEPER->value);

    Livewire::actingAs($keeper)
        ->test(DUTY_SETTINGS_COMPONENT, ['user' => $keeper])
        ->assertSee('Sur la page « Qui fait quoi »');
});

it('keeps the section away from a member the page does not list', function (): void {
    $member = User::factory()->create();
    $member->assignRole(Role::SUPERVISION->value);

    Livewire::actingAs($member)
        ->test(DUTY_SETTINGS_COMPONENT, ['user' => $member])
        ->assertDontSee('Sur la page « Qui fait quoi »');
});

it('saves the duty contact choices without touching the directory ones', function (): void {
    $member = User::factory()->create(['contact_visibility' => ['phone' => true]]);
    $member->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($member)
        ->test(DUTY_SETTINGS_COMPONENT, ['user' => $member])
        ->set('dutyShareEmail', false)
        ->set('dutySharePhone', true);

    $member->refresh();
    expect($member->sharesDutyContact('email'))->toBeFalse()
        ->and($member->sharesDutyContact('phone'))->toBeTrue()
        ->and($member->sharesContact('phone'))->toBeTrue();
});

it('keeps the duty choices when a directory toggle is flipped', function (): void {
    $member = User::factory()->create(['contact_visibility' => ['duty_phone' => true]]);
    $member->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($member)
        ->test(DUTY_SETTINGS_COMPONENT, ['user' => $member])
        ->set('shareEmail', true);

    expect($member->fresh()->sharesDutyContact('phone'))->toBeTrue();
});

it('saves the word left for the members', function (): void {
    $member = User::factory()->create();
    $member->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($member)
        ->test(DUTY_SETTINGS_COMPONENT, ['user' => $member])
        ->set('dutyBlurb', 'Arrêtez-moi le vendredi au bar.')
        ->call('saveDutyBlurb')
        ->assertHasNoErrors();

    expect($member->fresh()->duty_blurb)->toBe('Arrêtez-moi le vendredi au bar.');
});

it('refuses a word longer than a short sentence', function (): void {
    $member = User::factory()->create();
    $member->assignRole(Role::COMMITTEE->value);

    Livewire::actingAs($member)
        ->test(DUTY_SETTINGS_COMPONENT, ['user' => $member])
        ->set('dutyBlurb', str_repeat('a', 281))
        ->call('saveDutyBlurb')
        ->assertHasErrors(['dutyBlurb']);
});
