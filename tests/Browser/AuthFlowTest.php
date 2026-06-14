<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create([
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
    ]);
});

it('redirects guest to login when visiting dashboard', function (): void {
    visit(route('dashboard'))
        ->assertPathIs('/login');
});

it('logs in with valid credentials and lands on home', function (): void {
    visit(route('login'))
        ->type('#email', 'admin@test.com')
        ->type('#password', 'password')
        ->click('button[type="submit"]')
        ->assertPathIs('/')
        ->assertNoJavaScriptErrors();
});

it('shows error on wrong password', function (): void {
    visit(route('login'))
        ->type('#email', 'admin@test.com')
        ->type('#password', 'wrong-password')
        ->click('button[type="submit"]')
        ->assertPathIs('/login')
        ->assertSee('These credentials do not match our records.');
});

it('shows error on unknown email', function (): void {
    visit(route('login'))
        ->type('#email', 'nobody@test.com')
        ->type('#password', 'password')
        ->click('button[type="submit"]')
        ->assertPathIs('/login')
        ->assertSee('These credentials do not match our records.');
});

it('authenticated user visiting login is redirected away', function (): void {
    $this->actingAs($this->admin);

    visit(route('login'))
        ->assertPathIsNot('/login');
});

it('redirects to intended URL after login', function (): void {
    visit(route('admin.users.index'))
        ->assertPathIs('/login')
        ->type('#email', 'admin@test.com')
        ->type('#password', 'password')
        ->click('button[type="submit"]')
        ->assertPathContains('users');
});
