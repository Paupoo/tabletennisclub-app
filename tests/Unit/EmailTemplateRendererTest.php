<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use App\Domains\Shared\Enums\ContactReasonEnum;
use App\Services\ClubAdmin\Contact\EmailTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->renderer = new EmailTemplateRenderer;
});

it('substitutes contact variables in subject and body', function (): void {
    config()->set('app.name', 'Mon Club TT');

    EmailTemplate::factory()->create([
        'key' => 'greet',
        'subject' => 'Bonjour {{first_name}} ({{full_name}})',
        'body' => '{{first_name}} {{last_name}}, intérêt : {{interest}} chez {{club_name}}.',
        'apply_status' => null,
    ]);

    $contact = Contact::factory()->create([
        'first_name' => 'Alice',
        'last_name' => 'Martin',
        'interest' => ContactReasonEnum::JOIN_US,
    ]);

    $result = $this->renderer->render('greet', $contact);

    expect($result['subject'])->toBe('Bonjour Alice (Alice Martin)')
        ->and($result['body'])->toBe('Alice Martin, intérêt : ' . ContactReasonEnum::JOIN_US->getLabel() . ' chez Mon Club TT.')
        ->and($result['apply_status'])->toBeNull();
});

it('returns the apply_status carried by the template', function (): void {
    EmailTemplate::factory()->create([
        'key' => 'decline',
        'apply_status' => 'rejected',
    ]);

    $contact = Contact::factory()->create();

    expect($this->renderer->render('decline', $contact)['apply_status'])->toBe('rejected');
});

it('leaves unknown variables unchanged', function (): void {
    EmailTemplate::factory()->create([
        'key' => 'weird',
        'subject' => 'Subject',
        'body' => 'Hello {{first_name}} {{unknown_var}}',
    ]);

    $contact = Contact::factory()->create(['first_name' => 'Bob']);

    expect($this->renderer->render('weird', $contact)['body'])->toBe('Hello Bob {{unknown_var}}');
});

it('throws when the template key does not exist', function (): void {
    $contact = Contact::factory()->create();

    expect(fn () => $this->renderer->render('nope', $contact))
        ->toThrow(InvalidArgumentException::class, 'nope');
});

it('throws when the template is inactive', function (): void {
    EmailTemplate::factory()->inactive()->create(['key' => 'archived']);

    $contact = Contact::factory()->create();

    expect(fn () => $this->renderer->render('archived', $contact))
        ->toThrow(InvalidArgumentException::class, 'archived');
});
