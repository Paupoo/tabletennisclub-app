<?php

declare(strict_types=1);
use App\Models\ClubEvents\Interclub\Club;
use App\Providers\RouteServiceProvider;

pest()->group('auth');

test('new users can register', function (): void {
    Club::factory()->create(['licence' => config('app.club_licence')]);

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
