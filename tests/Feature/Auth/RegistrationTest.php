<?php

declare(strict_types=1);
use App\Providers\RouteServiceProvider;

pest()->group('auth');

test('new users can register', function (): void {
    $email = 'user_' . uniqid() . '@example.com';

    $response = $this->post('/register', [
        'first_name' => 'John',
        'last_name' => 'doe',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    
    $this->assertAuthenticated();
    $response->assertRedirect(RouteServiceProvider::HOME);
});
test('registration screen can be rendered', function (): void {
    $response = $this->get('/register');

    $response->assertStatus(200);
});
