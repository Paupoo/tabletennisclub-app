<?php

declare(strict_types=1);

namespace Tests\Feature\Contact;

use App\Models\ClubAdmin\Contact\Contact;
use App\Models\ClubAdmin\Users\User;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use Exception;
use Illuminate\Support\Facades\Log;
use Mockery;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

describe('ContactAdminControllerTest', function () {
    beforeEach(function () {
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->emailService = $this->mock(ContactEmailService::class);
        actingAs($this->admin);
    });

    /**
     * INDEX & SHOW
     */
    it('renders the contact index page with stats', function () {

        Contact::factory()->count(3)->create();

        get(route('clubAdmin.contacts.index'))
            ->assertOk()
            ->assertViewIs('clubAdmin.contacts.contact.index')
            ->assertViewHas(['contacts', 'stats', 'breadcrumbs']);
    });

    it('renders the contact show page', function () {
        $contact = Contact::factory()->create();

        get(route('clubAdmin.contacts.show', $contact))
            ->assertOk()
            ->assertViewIs('clubAdmin.contacts.contact.show')
            ->assertViewHas('contact');
    });

    /**
     * ACTIONS (Update / Delete)
     */
    it('updates a contact and redirects back', function () {
        $contact = Contact::factory()->create(['status' => 'pending']);

        // Mock data from UpdateContactRequest
        $newData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'status' => 'processed',
        ];

        put(route('clubAdmin.contacts.update', $contact), $newData)
            ->assertRedirect()
            ->assertSessionHas('success');

        expect($contact->refresh()->status)->toBe('processed');
    });

    it('deletes a contact and redirects to index', function () {
        $contact = Contact::factory()->create();

        delete(route('clubAdmin.contacts.destroy', $contact))
            ->assertRedirect(route('clubAdmin.contacts.index'))
            ->assertSessionHas('success');

        $this->assertModelMissing($contact);
    });

    /**
     * EMAIL SENDING (Templates)
     */
    it('redirects to compose view if template is custom', function () {
        $contact = Contact::factory()->create();

        post(route('clubAdmin.contacts.send-email', $contact), ['template' => 'custom'])
            ->assertRedirect(route('clubAdmin.contacts.compose-email', $contact));
    });

    it('sends a template email successfully', function () {
        $contact = Contact::factory()->create();

        $this->emailService
            ->shouldReceive('sendTemplate')
            ->once()
            ->andReturn('Email envoyé !');

        $this->post(route('clubAdmin.contacts.send-email', $contact), ['template' => 'welcome'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Email envoyé !');
    });

    /**
     * CUSTOM EMAIL
     */
    it('renders the compose email view', function () {
        $contact = Contact::factory()->create();

        get(route('clubAdmin.contacts.compose-email', $contact))
            ->assertOk()
            ->assertViewIs('clubAdmin.contacts.contact.compose-email');
    });

    it('sends a custom email successfully', function () {
        $contact = Contact::factory()->create();
        $data = [
            'subject' => 'Hello',
            'message' => 'World',
            'contact' => $contact,
            'send_copy' => true,
        ];

        $this->emailService
            ->shouldReceive('sendCustom')
            ->once()
            ->with(// On vérifie que c'est le même contact (via l'ID).
                Mockery::on(fn ($c) => $c->is($contact)),

                // On vérifie le contenu du tableau.
                Mockery::on(function ($mailData) {
                    return $mailData['subject'] === 'Hello' &&
                        $mailData['message'] === 'World';
                }),

                // On vérifie que c'est le bon utilisateur (via l'ID).
                Mockery::on(fn ($u) => $u->is($this->admin)),
                true
            );

        $this->post(route('clubAdmin.contacts.send-custom-email', $contact), $data)
            ->assertRedirect(route('clubAdmin.contacts.show', $contact))
            ->assertSessionHas('success');
    });

    it('catches exceptions when sending custom email', function () {
        $contact = Contact::factory()->create();

        $this->emailService
            ->shouldReceive('sendCustom')
            ->andThrow(new Exception('SMTP Error'));

        Log::shouldReceive('error')->once();

        post(route('clubAdmin.contacts.send-custom-email', $contact), [
            'subject' => 'Test',
            'message' => 'Fail',
        ])
            ->assertRedirect()
            ->assertSessionHas('error');
    });

})->group('contact');
