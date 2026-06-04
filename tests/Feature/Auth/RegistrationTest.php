<?php

declare(strict_types=1);

pest()->group('auth');

test('registration route is disabled (invite-only onboarding)', function (): void {
    $response = $this->get('/register');
    $response->assertStatus(404);
});

test('registration POST is disabled', function (): void {
    $response = $this->post('/register', [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
    ]);
    $response->assertStatus(404);
});
