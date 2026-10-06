<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

const CAMPAIGN_FORM_COMPONENT = 'pages::club-admin.feedback.campaign';

beforeEach(function (): void {
    $this->delegate = User::factory()->withRole(Role::FEEDBACK)->create();
});

it('keeps the campaign screens to the délégation', function (): void {
    $this->actingAs($this->delegate)->get(route('admin.feedback.campaigns.create'))->assertOk();
    $this->actingAs(User::factory()->isCommitteeMember()->create())->get(route('admin.feedback.campaigns.create'))->assertForbidden();
});

it('saves a draft, which sends nothing', function (): void {
    Livewire::actingAs($this->delegate)
        ->test(CAMPAIGN_FORM_COMPONENT)
        ->set('title', 'Votre avis sur la saison 2026-2027')
        ->set('opensOn', today()->addWeek()->toDateString())
        ->set('closesOn', today()->addWeeks(4)->toDateString())
        ->set('intro', 'Cinq minutes suffisent.')
        ->set('yearQuestion', 'Que pensez-vous des nouveaux horaires du mercredi ?')
        ->call('save')
        ->assertHasNoErrors();

    $campaign = FeedbackCampaign::sole();
    expect($campaign->isDraft())->toBeTrue()
        ->and($campaign->year_question)->toBe('Que pensez-vous des nouveaux horaires du mercredi ?')
        ->and($campaign->created_by_id)->toBe($this->delegate->id);
});

it('refuses a campaign that closes before it opens, or opens in the past', function (): void {
    Livewire::actingAs($this->delegate)
        ->test(CAMPAIGN_FORM_COMPONENT)
        ->set('title', 'Enquête')
        ->set('intro', 'Texte')
        ->set('opensOn', today()->subDay()->toDateString())
        ->set('closesOn', today()->subWeek()->toDateString())
        ->call('save')
        ->assertHasErrors(['opensOn', 'closesOn']);
});

it('schedules a draft', function (): void {
    $campaign = FeedbackCampaign::factory()->create();

    Livewire::actingAs($this->delegate)
        ->test(CAMPAIGN_FORM_COMPONENT, ['campaign' => $campaign])
        ->call('schedule')
        ->assertHasNoErrors();

    expect($campaign->fresh()->isDraft())->toBeFalse();
});

it('refuses to schedule a campaign over another one', function (): void {
    FeedbackCampaign::factory()->scheduled()->create(['opens_on' => today()->addDays(3), 'closes_on' => today()->addWeeks(2)]);
    $campaign = FeedbackCampaign::factory()->create(['opens_on' => today()->addWeek(), 'closes_on' => today()->addWeeks(5)]);

    Livewire::actingAs($this->delegate)
        ->test(CAMPAIGN_FORM_COMPONENT, ['campaign' => $campaign])
        ->call('schedule')
        ->assertHasErrors(['opensOn']);

    expect($campaign->fresh()->isDraft())->toBeTrue();
});

it('freezes an open campaign but its closing date and introduction', function (): void {
    $campaign = FeedbackCampaign::factory()->open()->create(['title' => 'Avant', 'year_question' => 'Question']);
    $closes = today()->addWeeks(5)->toDateString();

    Livewire::actingAs($this->delegate)
        ->test(CAMPAIGN_FORM_COMPONENT, ['campaign' => $campaign])
        ->set('title', 'Après')
        ->set('yearQuestion', 'Autre question')
        ->set('closesOn', $closes)
        ->set('intro', 'Nouvelle introduction')
        ->call('save')
        ->assertHasNoErrors();

    $campaign->refresh();
    expect($campaign->title)->toBe('Avant')
        ->and($campaign->year_question)->toBe('Question')
        ->and($campaign->closes_on->toDateString())->toBe($closes)
        ->and($campaign->intro)->toBe('Nouvelle introduction');
});

it('lists the campaigns, newest first', function (): void {
    FeedbackCampaign::factory()->scheduled()->create(['title' => 'Enquête 2025', 'opens_on' => today()->subYear(), 'closes_on' => today()->subYear()->addWeeks(3)]);
    FeedbackCampaign::factory()->create(['title' => 'Enquête 2026']);

    Livewire::actingAs($this->delegate)
        ->test('pages::club-admin.feedback.campaigns')
        ->assertSeeInOrder(['Enquête 2026', 'Enquête 2025']);
});
