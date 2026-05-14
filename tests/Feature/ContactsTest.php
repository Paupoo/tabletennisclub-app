<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Contact\Contact;
use App\Models\ClubAdmin\Users\User;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['is_admin' => true]);
});

describe('Contacts index', function (): void {
    it('redirects guests to login', function (): void {
        $this->get(route('admin.website.contacts.index'))
            ->assertRedirect(route('login'));
    });

    it('is accessible to admins', function (): void {
        $this->actingAs($this->admin)
            ->get(route('admin.website.contacts.index'))
            ->assertOk();
    });

    it('lists contacts', function (): void {
        Contact::factory()->create(['first_name' => 'Alice', 'last_name' => 'Smith']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->assertSee('Alice');
    });

    it('filters by search', function (): void {
        Contact::factory()->create(['first_name' => 'Bertrand', 'last_name' => 'Dupont', 'email' => 'bert@test.com', 'status' => 'new']);
        Contact::factory()->create(['first_name' => 'Clara', 'last_name' => 'Martin', 'email' => 'clara@test.com', 'status' => 'new']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('search', 'Bertrand')
            ->assertSee('Bertrand')
            ->assertDontSee('Clara');
    });

    it('filters by status', function (): void {
        Contact::factory()->create(['first_name' => 'New Contact', 'last_name' => 'A', 'status' => 'new']);
        Contact::factory()->create(['first_name' => 'Done Contact', 'last_name' => 'B', 'status' => 'processed']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('status', 'new')
            ->assertSee('New Contact')
            ->assertDontSee('Done Contact');
    });

    it('updates contact status inline', function (): void {
        $contact = Contact::factory()->create(['status' => 'new']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->call('updateStatus', $contact->id, 'processed');

        expect($contact->fresh()->status)->toBe('processed');
    });

    it('deletes a contact', function (): void {
        $contact = Contact::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->call('confirmDelete', $contact->id)
            ->call('delete');

        expect(Contact::find($contact->id))->toBeNull();
    });

    it('sends template email via ContactEmailService', function (): void {
        $contact = Contact::factory()->create(['status' => 'new']);

        $mock = Mockery::mock(ContactEmailService::class);
        $mock->shouldReceive('sendTemplate')
            ->once()
            ->with(Mockery::on(fn ($c) => $c->id === $contact->id), 'welcome')
            ->andReturn('Email welcome successfully sent');

        $this->app->instance(ContactEmailService::class, $mock);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->call('sendTemplateEmail', 'welcome');
    });
});
