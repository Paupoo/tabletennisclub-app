<?php

declare(strict_types=1);

use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('every rendered dialog carries an accessible name', function (): void {
    $html = Livewire::actingAs($this->createFakeAdmin())
        ->test('pages::club-admin.users.index')
        ->html();

    preg_match_all('/<dialog[^>]*>/', $html, $matches);

    expect($matches[0])->not->toBeEmpty('the page under test should render at least one modal');

    $nameless = array_values(array_filter(
        $matches[0],
        fn (string $tag): bool => ! str_contains($tag, 'aria-label'),
    ));

    expect($nameless)->toBe([], sprintf(
        "A dialog without aria-label is announced as \"dialog\" and nothing else:\n%s",
        implode("\n", $nameless),
    ));
});
