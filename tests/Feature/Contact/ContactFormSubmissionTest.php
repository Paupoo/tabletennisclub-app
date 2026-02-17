<?php

declare(strict_types=1);

use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use Illuminate\Support\Facades\Mail;

describe('Contact Form - Public Submission', function () {
    beforeEach(function () {
        Mail::fake();
    });

    it('stores valid contact form submission', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'phone' => '06 12 34 56 78',
            'interest' => 'JOIN_US',
            'message' => 'Je suis très intéressé par une adhésion au club.',
            'membership_family_members' => 2,
            'membership_competitors' => 1,
            'membership_training_sessions' => 5,
            'membership_total_cost' => 200,
            'consent' => true,
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect('/#contact')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contacts', [
            'first_name' => 'Jean',
            'email' => 'jean@test.com',
            'interest' => 'JOIN_US',
        ]);
    });

    it('sends emails after successful submission', function () {
        $data = [
            'first_name' => 'Marie',
            'last_name' => 'Martin',
            'email' => 'marie@test.com',
            'interest' => 'TRAINING',
            'message' => 'Intéressée par les entraînements.',
            'consent' => true,
        ];

        $this->post(route('contact.store'), $data);

        Mail::assertSent(ContactFormConfirmationEmail::class);
        Mail::assertSent(ContactFormNotificationEmail::class);
    });

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
    });

    it('rejects invalid email address', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'invalid-email',
            'interest' => 'INFORMATION',
            'message' => 'Test message',
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('requires consent acceptance', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'INFORMATION',
            'message' => 'Test message',
            // consent missing or false
        ]);

        $response->assertSessionHasErrors('consent');
    });

    it('validates message maximum length', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'INFORMATION',
            'message' => str_repeat('a', 2001), // Exceeds 2000 character limit
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('message');
    });

    it('validates membership fields as integers', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'MEMBERSHIP',
            'message' => 'Test message',
            'membership_family_members' => 'invalid',
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('membership_family_members');
    });

    it('limits family members to maximum of 10', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'MEMBERSHIP',
            'message' => 'Test message',
            'membership_family_members' => 15,
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('membership_family_members');
    });

    it('allows phone to be optional', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'INFORMATION',
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
    });

    it('preserves form data on validation error', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'invalid-email',
            'interest' => 'MEMBERSHIP',
            'message' => 'Test message',
            'consent' => true,
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertSessionHasErrors('email')
            ->assertSessionHasInput('first_name', 'Jean')
            ->assertSessionHasInput('last_name', 'Dupont');
    });

    it('implements rate limiting - allows 3 requests per 60 seconds', function () {
        $data = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'INFORMATION',
            'message' => 'Test message',
            'consent' => true,
        ];

        // First 3 requests should succeed
        $this->post(route('contact.store'), $data);
        $this->post(route('contact.store'), $data);
        $response = $this->post(route('contact.store'), $data);

        $response->assertSuccessful();

        // 4th request should be rate limited
        $response = $this->post(route('contact.store'), $data);
        $response->assertStatus(429);
    });

    it('has CSRF protection', function () {
        $response = $this->post(route('contact.store'), [], [
            'X-CSRF-TOKEN' => 'invalid-token',
        ]);

        $response->assertStatus(419); // Token mismatch
    });

    it('handles email sending errors gracefully', function () {
        Mail::shouldReceive('to->send')->andThrow(new \Exception('Mail service down'));

        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'INFORMATION',
            'message' => 'Test message',
            'consent' => true,
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect('/#contact')
            ->assertSessionHas('error', 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer.');
    });

    it('returns correct redirect URL on success', function () {
        $data = [
            'first_name' => 'Pierre',
            'last_name' => 'Bernard',
            'email' => 'pierre@test.com',
            'interest' => 'TOURNAMENT',
            'message' => 'Intéressé par les tournois.',
            'consent' => true,
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect('/#contact');
    });
});
