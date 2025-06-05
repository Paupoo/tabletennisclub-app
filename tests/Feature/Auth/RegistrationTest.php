<?php

declare(strict_types=1);
use App\Providers\RouteServiceProvider;

test('new users can register', function () {
    $response = $this->post('/register', [
        'first_name' => 'John',
        'last_name' => 'doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(RouteServiceProvider::HOME);
});
test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});
