<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = $this->createFakeAdmin();
});

test('the payment badge is written in French on every breakpoint', function (): void {
    User::factory()->create(['first_name' => 'Lena', 'last_name' => 'Adam']);

    // The table and the mobile cards are rendered in the same response, so a single
    // untranslated call site is enough to leak English to whoever reads the list.
    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->assertSee(__('Unpaid'))
        ->assertDontSee('Unpaid', escape: false);
});
