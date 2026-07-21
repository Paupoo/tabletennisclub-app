<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\PlayerExperienceEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

describe('contact profile capture (drawer)', function (): void {
    it('persists each profile field on the selected contact', function (): void {
        $contact = Contact::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('profileAgeCategory', AgeCategoryEnum::TEEN->value)
            ->set('profileExperience', PlayerExperienceEnum::FEW_YEARS->value)
            ->set('profileWantsCompetition', '1')
            ->set('profileFamilyCanDrive', '0')
            ->set('profilePreferredDays', ['monday', 'wednesday'])
            ->call('updateContactProfile');

        $contact->refresh();

        expect($contact->age_category)->toBe(AgeCategoryEnum::TEEN)
            ->and($contact->experience)->toBe(PlayerExperienceEnum::FEW_YEARS)
            ->and($contact->wants_competition)->toBeTrue()
            ->and($contact->family_can_drive)->toBeFalse()
            ->and($contact->preferred_days)->toBe(['monday', 'wednesday']);
    });

    it('treats blank tri-state values as not provided (null)', function (): void {
        $contact = Contact::factory()->withProfile()->create([
            'wants_competition' => true,
            'family_can_drive' => true,
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('profileWantsCompetition', '')
            ->set('profileFamilyCanDrive', '')
            ->call('updateContactProfile');

        $contact->refresh();

        expect($contact->wants_competition)->toBeNull()
            ->and($contact->family_can_drive)->toBeNull();
    });

    it('loads existing profile values when opening the detail', function (): void {
        $contact = Contact::factory()->create([
            'age_category' => AgeCategoryEnum::CHILD,
            'experience' => PlayerExperienceEnum::NONE,
            'wants_competition' => false,
            'preferred_days' => ['friday'],
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->call('openDetail', $contact->id)
            ->assertSet('profileAgeCategory', AgeCategoryEnum::CHILD->value)
            ->assertSet('profileExperience', PlayerExperienceEnum::NONE->value)
            ->assertSet('profileWantsCompetition', '0')
            ->assertSet('profilePreferredDays', ['friday']);
    });
});

describe('template prefill & send', function (): void {
    it('prefills the email editor from a template and stores the apply status', function (): void {
        $contact = Contact::factory()->create(['first_name' => 'Marie', 'status' => 'new']);
        $template = EmailTemplate::factory()->appliesStatus('processed')->create([
            'key' => 'welcome',
            'subject' => 'Bienvenue {{first_name}}',
            'body' => 'Salut {{first_name}}',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('selectedTemplateKey', $template->key)
            ->call('applyTemplate')
            ->assertSet('emailSubject', 'Bienvenue Marie')
            ->assertSet('emailBody', 'Salut Marie')
            ->assertSet('pendingApplyStatus', 'processed')
            ->assertSet('emailModal', true);
    });

    it('opens the editor pre-filled as soon as a template is picked (no extra click)', function (): void {
        $contact = Contact::factory()->create(['first_name' => 'Lina', 'status' => 'new']);
        EmailTemplate::factory()->create([
            'key' => 'welcome',
            'subject' => 'Salut {{first_name}}',
            'body' => 'Bonjour {{first_name}}',
        ]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('selectedTemplateKey', 'welcome') // simulates wire:model.live selection
            ->assertSet('emailModal', true)
            ->assertSet('emailSubject', 'Salut Lina')
            ->assertSet('emailBody', 'Bonjour Lina')
            ->assertSet('selectedTemplateKey', ''); // dropdown reset so it can re-trigger
    });

    it('opens a blank editor for a custom email', function (): void {
        $contact = Contact::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('emailSubject', 'leftover')
            ->set('emailBody', 'leftover body')
            ->call('openCustomEmail')
            ->assertSet('emailModal', true)
            ->assertSet('emailSubject', '')
            ->assertSet('emailBody', '')
            ->assertSet('selectedTemplateKey', '');
    });

    it('resets editor state when closed', function (): void {
        $contact = Contact::factory()->create();
        EmailTemplate::factory()->create(['key' => 'welcome', 'subject' => 'S', 'body' => 'B']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('selectedTemplateKey', 'welcome')
            ->call('closeEmailModal')
            ->assertSet('emailModal', false)
            ->assertSet('emailSubject', '')
            ->assertSet('emailBody', '')
            ->assertSet('pendingApplyStatus', null);
    });

    it('applies the pending status to the contact after sending', function (): void {
        Mail::fake();
        $contact = Contact::factory()->create(['status' => 'new']);
        $template = EmailTemplate::factory()->appliesStatus('processed')->create(['key' => 'welcome']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('selectedTemplateKey', $template->key)
            ->call('applyTemplate')
            ->call('sendCustomEmail');

        expect($contact->fresh()->status)->toBe('processed');
    });

    it('does not change status for a free email without a template', function (): void {
        Mail::fake();
        $contact = Contact::factory()->create(['status' => 'new']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('emailSubject', 'Hello')
            ->set('emailBody', 'Just a free message')
            ->call('sendCustomEmail');

        expect($contact->fresh()->status)->toBe('new');
    });

    it('resets the pending status after sending', function (): void {
        Mail::fake();
        $contact = Contact::factory()->create(['status' => 'new']);
        $template = EmailTemplate::factory()->appliesStatus('processed')->create(['key' => 'welcome']);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('selectedTemplateKey', $template->key)
            ->call('applyTemplate')
            ->call('sendCustomEmail')
            ->assertSet('pendingApplyStatus', null)
            ->assertSet('selectedTemplateKey', '');
    });
});

describe('inbox filters', function (): void {
    it('filters by age category', function (): void {
        Contact::factory()->create(['first_name' => 'TeenOne', 'last_name' => 'A', 'age_category' => AgeCategoryEnum::TEEN]);
        Contact::factory()->create(['first_name' => 'AdultTwo', 'last_name' => 'B', 'age_category' => AgeCategoryEnum::ADULT]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('ageCategory', AgeCategoryEnum::TEEN->value)
            ->assertSee('TeenOne')
            ->assertDontSee('AdultTwo');
    });

    it('filters by experience', function (): void {
        Contact::factory()->create(['first_name' => 'RankedOne', 'last_name' => 'A', 'experience' => PlayerExperienceEnum::RANKED]);
        Contact::factory()->create(['first_name' => 'NoviceTwo', 'last_name' => 'B', 'experience' => PlayerExperienceEnum::NONE]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('experience', PlayerExperienceEnum::RANKED->value)
            ->assertSee('RankedOne')
            ->assertDontSee('NoviceTwo');
    });

    it('filters by competition intent', function (): void {
        Contact::factory()->create(['first_name' => 'WantsComp', 'last_name' => 'A', 'wants_competition' => true]);
        Contact::factory()->create(['first_name' => 'NoComp', 'last_name' => 'B', 'wants_competition' => false]);

        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('wantsCompetition', '1')
            ->assertSee('WantsComp')
            ->assertDontSee('NoComp');
    });

    it('clears the new filters', function (): void {
        Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('ageCategory', AgeCategoryEnum::TEEN->value)
            ->set('experience', PlayerExperienceEnum::RANKED->value)
            ->set('wantsCompetition', '1')
            ->call('clearFilters')
            ->assertSet('ageCategory', '')
            ->assertSet('experience', '')
            ->assertSet('wantsCompetition', '');
    });

    it('exposes chips for the new filters', function (): void {
        $component = Livewire::actingAs($this->admin)
            ->test('pages::website.contacts.index')
            ->set('ageCategory', AgeCategoryEnum::TEEN->value)
            ->set('experience', PlayerExperienceEnum::RANKED->value)
            ->set('wantsCompetition', '1');

        $keys = collect($component->instance()->getFilterChips())->pluck('key');

        expect($keys)->toContain('ageCategory', 'experience', 'wantsCompetition');
    });
});

describe('authorization', function (): void {
    it('lets a secretary manage the contact profile', function (): void {
        $secretary = User::factory()->isCommitteeMember()->withRole(Role::CONTACTS)->create([
            'committee_role' => CommitteeRolesEnum::SECRETARY,
        ]);
        $contact = Contact::factory()->create();

        Livewire::actingAs($secretary)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('profileAgeCategory', AgeCategoryEnum::ADULT->value)
            ->call('updateContactProfile');

        expect($contact->fresh()->age_category)->toBe(AgeCategoryEnum::ADULT);
    });

    it('forbids a non-manager committee member from editing the profile', function (): void {
        $treasurer = User::factory()->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);
        $contact = Contact::factory()->create(['age_category' => AgeCategoryEnum::CHILD]);

        Livewire::actingAs($treasurer)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('profileAgeCategory', AgeCategoryEnum::ADULT->value)
            ->call('updateContactProfile')
            ->assertForbidden();

        expect($contact->fresh()->age_category)->toBe(AgeCategoryEnum::CHILD);
    });

    it('forbids a non-manager committee member from sending email', function (): void {
        Mail::fake();
        $treasurer = User::factory()->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);
        $contact = Contact::factory()->create(['status' => 'new']);

        Livewire::actingAs($treasurer)
            ->test('pages::website.contacts.index')
            ->set('selectedContactId', $contact->id)
            ->set('emailSubject', 'Hi')
            ->set('emailBody', 'Body')
            ->call('sendCustomEmail')
            ->assertForbidden();

        Mail::assertNothingSent();
    });
});
