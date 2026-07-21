<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

describe('listing', function (): void {
    it('displays existing templates with their key and badges', function (): void {
        $template = EmailTemplate::factory()->questionnaire()->create([
            'name' => 'Welcome aboard',
            'key' => 'welcome_aboard',
        ]);
        $inactive = EmailTemplate::factory()->inactive()->create(['name' => 'Old reply']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->assertSee('Welcome aboard')
            ->assertSee('welcome_aboard')
            ->assertSee('Old reply');
    });
});

describe('create', function (): void {
    it('persists a new template with an applied status and questionnaire flag', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openCreate')
            ->set('formName', 'Trial invite')
            ->set('formKey', 'trial_invite_custom')
            ->set('formSubject', 'Come and try')
            ->set('formBody', 'Hello {{first_name}}')
            ->set('formApplyStatus', 'processed')
            ->set('formIsQuestionnaire', true)
            ->call('saveTemplate')
            ->assertHasNoErrors()
            ->assertSet('formModal', false);

        $template = EmailTemplate::where('key', 'trial_invite_custom')->first();

        expect($template)->not->toBeNull()
            ->and($template->name)->toBe('Trial invite')
            ->and($template->subject)->toBe('Come and try')
            ->and($template->body)->toBe('Hello {{first_name}}')
            ->and($template->apply_status)->toBe('processed')
            ->and($template->is_questionnaire)->toBeTrue()
            ->and($template->is_active)->toBeTrue();
    });

    it('persists a new template without an applied status', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openCreate')
            ->set('formName', 'Plain reply')
            ->set('formKey', 'plain_reply')
            ->set('formSubject', 'Reply')
            ->set('formBody', 'Hi')
            ->set('formApplyStatus', '')
            ->set('formIsQuestionnaire', false)
            ->call('saveTemplate')
            ->assertHasNoErrors();

        expect(EmailTemplate::where('key', 'plain_reply')->first()->apply_status)->toBeNull();
    });

    it('requires name, key, subject and body', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openCreate')
            ->set('formName', '')
            ->set('formKey', '')
            ->set('formSubject', '')
            ->set('formBody', '')
            ->call('saveTemplate')
            ->assertHasErrors(['formName', 'formKey', 'formSubject', 'formBody']);
    });

    it('rejects a duplicate key on create', function (): void {
        EmailTemplate::factory()->create(['key' => 'taken_key']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openCreate')
            ->set('formName', 'Dup')
            ->set('formKey', 'taken_key')
            ->set('formSubject', 'S')
            ->set('formBody', 'B')
            ->call('saveTemplate')
            ->assertHasErrors(['formKey']);
    });

    it('rejects an invalid apply status', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openCreate')
            ->set('formName', 'Bad')
            ->set('formKey', 'bad_status')
            ->set('formSubject', 'S')
            ->set('formBody', 'B')
            ->set('formApplyStatus', 'archived')
            ->call('saveTemplate')
            ->assertHasErrors(['formApplyStatus']);
    });
});

describe('update', function (): void {
    it('modifies an existing template', function (): void {
        $template = EmailTemplate::factory()->create([
            'name' => 'Before',
            'key' => 'editable_key',
            'subject' => 'Old',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openEdit', $template->id)
            ->assertSet('formName', 'Before')
            ->set('formName', 'After')
            ->set('formSubject', 'New subject')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        $template->refresh();

        expect($template->name)->toBe('After')
            ->and($template->subject)->toBe('New subject');
    });

    it('keeps the key valid when editing the same template', function (): void {
        $template = EmailTemplate::factory()->create(['key' => 'my_key']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openEdit', $template->id)
            ->set('formName', 'Renamed')
            ->call('saveTemplate')
            ->assertHasNoErrors();
    });

    it('locks the key field for system starter templates', function (): void {
        $system = EmailTemplate::factory()->create(['key' => 'welcome']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('openEdit', $system->id)
            ->assertSet('keyLocked', true)
            ->set('formName', 'Renamed welcome')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        // Even if the key field is tampered with, the original key is preserved.
        expect($system->fresh()->key)->toBe('welcome');
    });
});

describe('delete', function (): void {
    it('deletes a template after confirmation', function (): void {
        $template = EmailTemplate::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('confirmDelete', $template->id)
            ->assertSet('deleteModal', true)
            ->call('deleteTemplate')
            ->assertSet('deleteModal', false);

        expect(EmailTemplate::find($template->id))->toBeNull();
    });
});

describe('toggle active', function (): void {
    it('toggles the is_active flag', function (): void {
        $template = EmailTemplate::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('toggleActive', $template->id);

        expect($template->fresh()->is_active)->toBeFalse();

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.email-templates')
            ->call('toggleActive', $template->id);

        expect($template->fresh()->is_active)->toBeTrue();
    });
});

describe('authorization', function (): void {
    it('lets a secretary access and create templates', function (): void {
        $secretary = User::factory()->isCommitteeMember()->withRole(Role::CONTACTS)->create([
            'committee_role' => CommitteeRolesEnum::SECRETARY,
        ]);

        Livewire::actingAs($secretary)
            ->test('pages::website.contacts.email-templates')
            ->call('openCreate')
            ->set('formName', 'Secretary template')
            ->set('formKey', 'secretary_template')
            ->set('formSubject', 'S')
            ->set('formBody', 'B')
            ->call('saveTemplate')
            ->assertHasNoErrors();

        expect(EmailTemplate::where('key', 'secretary_template')->exists())->toBeTrue();
    });

    it('forbids a non-manager committee member from mounting the screen', function (): void {
        $treasurer = User::factory()->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);

        Livewire::actingAs($treasurer)
            ->test('pages::website.contacts.email-templates')
            ->assertForbidden();
    });

    it('forbids a non-manager committee member at the route level', function (): void {
        $treasurer = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);

        $this->actingAs($treasurer)
            ->get(route('admin.website.contacts.email-templates'))
            ->assertForbidden();
    });

    it('rejects an unauthenticated visitor at the route level', function (): void {
        $this->get(route('admin.website.contacts.email-templates'))
            ->assertRedirect(route('login'));
    });
});
