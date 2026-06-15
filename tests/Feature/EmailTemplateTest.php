<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\EmailTemplate;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Database\QueryException;

it('persists and casts an email template', function (): void {
    $template = EmailTemplate::factory()->create([
        'key' => 'custom_key',
        'is_questionnaire' => true,
        'is_active' => false,
    ]);

    $fresh = $template->fresh();

    expect($fresh->key)->toBe('custom_key')
        ->and($fresh->is_questionnaire)->toBeTrue()
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->subject)->toBeString()
        ->and($fresh->body)->toBeString();
});

it('exposes a questionnaire factory state', function (): void {
    expect(EmailTemplate::factory()->questionnaire()->create()->is_questionnaire)->toBeTrue();
});

it('seeds the starter set of templates with the expected keys', function (): void {
    $this->seed(EmailTemplateSeeder::class);

    $keys = EmailTemplate::pluck('key')->all();

    expect($keys)->toContain(
        'welcome',
        'membership_info',
        'request_info',
        'polite_decline',
        'info_request_questionnaire',
        'trial_invite',
    );
});

it('assigns the apply_status to the relevant seeded templates', function (): void {
    $this->seed(EmailTemplateSeeder::class);

    expect(EmailTemplate::where('key', 'polite_decline')->value('apply_status'))->toBe('rejected')
        ->and(EmailTemplate::where('key', 'welcome')->value('apply_status'))->toBe('processed')
        ->and(EmailTemplate::where('key', 'info_request_questionnaire')->value('is_questionnaire'))->toBeTrue();
});

it('keeps the template keys unique', function (): void {
    EmailTemplate::factory()->create(['key' => 'dup']);

    expect(fn () => EmailTemplate::factory()->create(['key' => 'dup']))
        ->toThrow(QueryException::class);
});
