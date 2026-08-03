<?php

declare(strict_types=1);

use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('the back office lets the reader zoom in', function (): void {
    // WCAG 1.4.4 asks for 200% resize without loss of function. maximum-scale
    // takes the pinch gesture away, and it is the only one a member has.
    $response = $this->actingAs($this->createFakeAdmin())->get(route('dashboard'));

    $response->assertOk();

    expect($response->getContent())
        ->toContain('name="viewport"')
        ->not->toContain('maximum-scale');
});

test('the guest layouts let the reader zoom in', function (): void {
    expect($this->get(route('login'))->getContent())->not->toContain('maximum-scale');
});
