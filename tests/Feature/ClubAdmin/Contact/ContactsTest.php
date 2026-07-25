<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Http\Requests\ClubAdmin\Contact\UpdateContactRequest;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
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

    it('prefills the editor from a template instead of sending directly', function (): void {
        $contact = Contact::factory()->create(['first_name' => 'Léa', 'status' => 'new']);
        $template = EmailTemplate::factory()->create([
            'key' => 'welcome',
            'subject' => 'Bonjour {{first_name}}',
            'body' => 'Coucou {{first_name}}',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('selectedTemplateKey', $template->key)
            ->call('applyTemplate')
            ->assertSet('emailSubject', 'Bonjour Léa')
            ->assertSet('emailModal', true);

        expect($contact->fresh()->status)->toBe('new');
    });
});

describe('Contact statuses', function (): void {
    it('offers new, processed and rejected only', function (): void {
        $options = Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->viewData('statusOptions');

        expect(array_column($options, 'id'))->toBe(['new', 'processed', 'rejected']);
    });

    it('counts the inbox without a pending bucket', function (): void {
        Contact::factory()->count(2)->create(['status' => 'new']);
        Contact::factory()->create(['status' => 'processed']);
        Contact::factory()->create(['status' => 'rejected']);

        expect(Contact::getStatusStats())
            ->toHaveKeys(['totalNew', 'totalProcessed', 'totalRejected'])
            ->not->toHaveKey('totalPending');
    });

    it('accepts the three remaining statuses on the update request and refuses pending', function (string $status, bool $passes): void {
        $validator = Validator::make(
            ['status' => $status],
            (new UpdateContactRequest)->rules(),
        );

        expect($validator->passes())->toBe($passes);
    })->with([
        ['new', true],
        ['processed', true],
        ['rejected', true],
        ['pending', false],
    ]);
});
