<?php

declare(strict_types=1);

use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use Illuminate\Support\Facades\Mail;

describe('Contact Form Submission', function () {
    beforeEach(function () {
        Mail::fake();
        config(['app.club_email' => 'test-admin@example.com']);
    });

    it('stores valid contact form submission', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'phone' => '06 12 34 56 78',
            'interest' => 'join_us',
            'message' => 'Je suis très intéressé par une adhésion au club.',
            'consent' => true,
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect('/#contact')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Jean',
            'email' => 'jean@test.com',
            'interest' => 'join_us',
        ]);

        // Verify emails were sent
        Mail::assertSent(ContactFormConfirmationEmail::class);
        Mail::assertSent(ContactFormNotificationEmail::class);
    })->group('contact');

    it('sends confirmation email to the contact', function () {
        $data = [
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => 'marie@test.com',
            'interest' => 'info_interclubs',
            'message' => 'Informations sur les interclubs svp.',
            'consent' => true,
        ];

        $this->post(route('contact.store'), $data);

        Mail::assertSent(ContactFormConfirmationEmail::class, function ($mail) {
            return $mail->hasTo('marie@test.com');
        });
    })->group('contact');

    it('sends notification email to club admin', function () {
        $data = [
            'first_name' => 'Pierre',
            'last_name' => 'Bernard',
            'email' => 'pierre@test.com',
            'interest' => 'partnership',
            'message' => 'Je suis intéressé par un partenariat.',
            'consent' => true,
        ];

        $this->post(route('contact.store'), $data);

        Mail::assertSent(ContactFormNotificationEmail::class, function ($mail) {
            return $mail->hasTo(config('app.club_email'));
        });
    })->group('contact');

    it('validates required fields', function () {
        $response = $this->post(route('contact.store'), [
            'consent' => true,
        ]);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'email',
            'interest',
            'message',
        ]);
    })->group('contact');

    it('rejects invalid email address', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'invalid-email',
            'interest' => 'join_us',
            'message' => 'Test message',
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('email');
    })->group('contact');

    it('requires consent acceptance', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'join_us',
            'message' => 'Test message',
            // consent missing or false
        ]);

        $response->assertSessionHasErrors('consent');
    })->group('contact');

    it('validates message maximum length', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'join_us',
            'message' => str_repeat('a', 2001), // Exceeds 2000 character limit
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('message');
    })->group('contact');

    it('allows phone to be optional', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'join_us',
            'message' => 'Test without phone',
            'consent' => true,
            // phone is omitted
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect('/#contact')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'email' => 'jean@test.com',
            'phone' => null,
        ]);
    })->group('contact');

    it('returns correct redirect URL on success', function () {
        $data = [
            'first_name' => 'Sophie',
            'last_name' => 'Laurent',
            'email' => 'sophie@test.com',
            'interest' => 'trial',
            'message' => 'Je voudrais faire un essai.',
            'consent' => true,
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect('/#contact');
    })->group('contact');
});
