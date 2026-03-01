<?php

declare(strict_types=1);

namespace Tests\Feature\Contact;

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\ThrottleRequests;

// ─────────────────────────────────────────────────────────────
// Suite : Security
// ─────────────────────────────────────────────────────────────

describe('Contact Form - Public Submission', function () {

    it('has CSRF protection', function () {
        $this->withoutMiddleware(ThrottleRequests::class);
        $response = $this->postJson(route('contact.store'), [], [
            'X-CSRF-TOKEN' => 'invalid-token',
        ]);

        $response->assertStatus(419); // Token mismatch
    });

    it('implements rate limiting - allows 3 requests per 60 seconds', function () {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $data = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'INFO_INTERCLUBS',
            'message' => 'Test message',
            'consent' => true,
        ];

        // First 3 requests should succeed
        $this->post(route('contact.store'), $data);
        $this->post(route('contact.store'), $data);
        $response = $this->post(route('contact.store'), $data);

        $response->assertStatus(302);

        // 4th request should be rate limited
        $response = $this->post(route('contact.store'), $data);
        $response->assertStatus(429);
    });

    it('preserves form data on validation error', function () {
        // 1. On s'assure que les middlewares de session sont actifs
        // Ne désactive QUE le CSRF
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $data = [
            'first_name' => 'Jean',
            'email' => 'invalid-email',
        ];

        // 2. On simule qu'on vient de l'accueil
        $response = $this->from(route('home'))
            ->post(route('contact.store'), $data);

        // 4. Assertions
        $response->assertStatus(302);
        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors('email');
    });

})->group('contact');

// ─────────────────────────────────────────────────────────────
// Suite : Form validation
// ─────────────────────────────────────────────────────────────

describe('Contact Form validations', function () {
    beforeEach(function () {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withoutMiddleware(ThrottleRequests::class);
    });

    // Full form ──────────────────────────

    it('stores valid contact form submission', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'phone' => '06 12 34 56 78',
            'interest' => 'JOIN_US',
            'membership_family_members' => 2,
            'membership_competitors' => 1,
            'membership_training_sessions' => 0,
            'membership_total_cost' => 150,
            'message' => 'Je suis très intéressé par une adhésion au club.',
            'consent' => true,
        ];

        $response = $this->post(route('contact.store'), $data);
        $response->assertStatus(302);
    });

    it('preserves form data on validation error', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'invalid-email',
            'interest' => 'PARTNERSHIP',
            'message' => 'Test message',
            'consent' => true,
        ];

        $response = $this->from(route('home'))
            ->post(route('contact.store'), $data);

        $response->assertSessionHasErrors('email')
            ->assertSessionHasInput('first_name', 'Jean')
            ->assertSessionHasInput('last_name', 'Dupont');
    });

    // Required fields ────────────────────

    it('validates all required fields with custom messages', function () {
        $response = $this->post(route('contact.store'), []);

        $response->assertSessionHasErrors([
            'first_name' => 'Le prénom est obligatoire.',
            'last_name' => 'Le nom est obligatoire.',
            'email' => 'L\'adresse email est obligatoire.',
            'interest' => 'Veuillez sélectionner votre intérêt.',
            'message' => 'Le message est obligatoire.',
            'consent' => 'Vous devez accepter les conditions.',
        ]);
    });

    it('rejects invalid email address', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'invalid-email',
            'interest' => 'INFO_INTERCLUBS',
            'message' => 'Test message',
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('email');
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

    it('requires consent acceptance', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'INFO_INTERCLUBS',
            'message' => 'Test message',
            // consent missing or false
        ]);

        $response->assertSessionHasErrors('consent');
    });

    // Optional fields ────────────────────

    it('allows optional fields to be null', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'JOIN_US',
            'message' => 'Hello',
            'consent' => true,
            'phone' => null,
            'membership_family_members' => null,
        ];

        $response = $this->post(route('contact.store'), $data);

        // Si pas d'erreurs de session, c'est que c'est validé
        $response->assertSessionHasNoErrors();
    });

    it('validates message maximum length', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'INFO_INTERCLUBS',
            'message' => str_repeat('a', 2001), // Exceeds 2000 character limit
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('message');
    });

    it('allows phone to be optional', function () {
        $data = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@test.com',
            'interest' => 'INFO_INTERCLUBS',
            'message' => 'Test without phone',
            'consent' => true,
            // phone is omitted
        ];

        $response = $this->post(route('contact.store'), $data);

        $response->assertRedirect(route('home'))
            ->assertSessionHas('success');
    });

    it('validates optional membership fields (must be numeric under 10)', function () {
        $response = $this->post(route('contact.store'), [
            'membership_family_members' => 11, // Max is 10
            'membership_total_cost' => 'not-a-number', // Must be numeric
        ]);

        $response->assertSessionHasErrors([
            'membership_family_members',
            'membership_total_cost',
        ]);
    });

    it('validates membership fields as integers', function () {
        $response = $this->post(route('contact.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'interest' => 'PARTNERSHIP',
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
            'interest' => 'PARTNERSHIP',
            'message' => 'Test message',
            'membership_family_members' => 15,
            'consent' => true,
        ]);

        $response->assertSessionHasErrors('membership_family_members');
    });

})->group('contact');
