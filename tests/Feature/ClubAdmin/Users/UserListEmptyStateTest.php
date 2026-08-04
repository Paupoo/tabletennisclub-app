<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = $this->createFakeAdmin();
});

/*
 * An empty list means one of two things, and the reader cannot tell them apart:
 * nothing exists yet, or the filters exclude everything. Both used to render
 * "Try adjusting your search or filters" — advice that is absurd for a club
 * opening its first season with nothing recorded at all.
 *
 * The branch that offers creation is exercised on the component, because the
 * members list can never actually be empty: whoever is reading it is a member.
 */
test('an unfiltered empty list offers the way to create the first record', function (): void {
    $html = Blade::render(
        '<x-admin.shared.list-empty-state :heading="$h" :create-label="$l" :create-href="$href" />',
        ['h' => 'No users found', 'l' => 'Create a member', 'href' => '/admin/club-admin/users/create'],
    );

    expect($html)
        ->toContain('Create a member')
        ->toContain('/admin/club-admin/users/create')
        ->not->toContain('clearFilters');
});

test('a filtered empty list offers to clear the filters instead', function (): void {
    User::factory()->count(2)->create();

    $html = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->set('search', 'zzzzz-aucun-membre-ne-porte-ce-nom')
        ->html();

    $heading = __('No users found');
    $at = strpos($html, (string) $heading);

    expect($at)->not->toBeFalse('the empty state should be rendered');

    $block = substr($html, (int) $at, 900);

    // The header carries a Create button too, so the assertion looks inside the
    // empty state rather than anywhere on the page.
    expect($block)
        ->toContain('clearFilters')
        ->not->toContain(route('admin.users.create', absolute: false));
});
