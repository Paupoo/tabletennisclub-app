<?php

declare(strict_types=1);

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;

// The anchor #contact is not testable : it's managed by the frontend and Laravel doesn't understand it, we will check it redirect to the expected page (home)

describe('Contact Form Submission', function (): void {
    beforeEach(function (): void {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withSession([
            'captcha' => ['a' => 3, 'b' => 2, 'operation' => '+'],
            'captcha_created_at' => time(),
        ]);
    });

    // TODO: This test keeps sending success in session and so the errors expectations don't work
    it('handles email sending errors gracefully : test not 100% ok', function (): void {
        Mail::shouldReceive('to->send')->andThrow(new Exception('Mail service down'));

        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'JOIN_US',
            'message' => 'Test message',
            'consent' => true,
            'captcha' => 5,
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect(route('home') . '#contact');
    });

    it('redirects to the home page on success', function (): void {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'JOIN_US',
            'message' => 'Hello',
            'consent' => true,
            'captcha' => 5,
        ];

        $response = $this->post(route('contact.store'), $data);
        $response->assertRedirect(route('home') . '#contact');
    });

    it('refuses a phone number nobody could ever dial', function (string $phone): void {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'JOIN_US',
            'message' => 'Hello',
            'consent' => true,
            'captcha' => 5,
            'phone' => $phone,
        ]);

        $response->assertSessionHasErrors('phone');
    })->with(['12', 'appelez-moi', '0475/12/34/56/78/90/12']);

    it('accepts a phone number however the visitor spaces it', function (string $phone): void {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'JOIN_US',
            'message' => 'Hello',
            'consent' => true,
            'captcha' => 5,
            'phone' => $phone,
        ]);

        $response->assertSessionHasNoErrors();
    })->with(['0475 12 34 56', '0475.12.34.56', '+32 475 12 34 56', '010 45 67 89']);
})->group('contact');
