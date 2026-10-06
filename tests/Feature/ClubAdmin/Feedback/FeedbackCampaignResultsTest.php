<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Services\CampaignResults;
use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->campaign = FeedbackCampaign::factory()->open()->create(['title' => 'Enquête 2026-2027', 'year_question' => 'Et les horaires ?']);
    $this->seat = User::factory()->isCommitteeMember()->create();
});

it('reads the mean rating and the spread of the answers', function (): void {
    foreach ([5, 4, 4, 2] as $rating) {
        FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $this->campaign->id, 'rating' => $rating]);
    }

    $results = app(CampaignResults::class);

    expect($results->average($this->campaign))->toBe(3.8)
        ->and($results->distribution($this->campaign))->toBe([5 => 1, 4 => 2, 3 => 0, 2 => 1, 1 => 0])
        ->and($results->responses($this->campaign))->toBe(4);
});

it('keeps counting the rating of an answer whose comment was hidden', function (): void {
    $response = FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $this->campaign->id, 'rating' => 1]);
    FeedbackEntry::factory()->anonymous()->create([
        'feedback_campaign_response_id' => $response->id,
        'feedback_theme_id' => FeedbackTheme::query()->firstOrFail()->id,
        'hidden_at' => now(),
    ]);

    expect(app(CampaignResults::class)->average($this->campaign))->toBe(1.0);
});

it('reads the seasons side by side', function (): void {
    $past = FeedbackCampaign::factory()->scheduled()->create(['title' => 'Enquête 2025-2026', 'opens_on' => today()->subYear(), 'closes_on' => today()->subYear()->addWeeks(3)]);
    FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $past->id, 'rating' => 3]);
    FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $this->campaign->id, 'rating' => 5]);
    FeedbackCampaign::factory()->create(['title' => 'Brouillon']);

    expect(app(CampaignResults::class)->trend())->toBe([
        ['title' => 'Enquête 2025-2026', 'average' => 3.0],
        ['title' => 'Enquête 2026-2027', 'average' => 5.0],
    ]);
});

it('shows the results tab to the committee, with the answers to the question of the year', function (): void {
    $signer = User::factory()->create(['first_name' => 'Sophie', 'last_name' => 'Renard']);
    FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $this->campaign->id, 'rating' => 4, 'user_id' => $signer->id, 'year_answer' => 'Très bien, merci.']);
    FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $this->campaign->id, 'rating' => 5, 'year_answer' => 'Parfait.']);

    Livewire::withQueryParams(['tab' => 'results'])
        ->actingAs($this->seat)
        ->test('pages::club-admin.feedback.index')
        ->assertSee('Enquête 2026-2027')
        ->assertSee('4,5')
        ->assertSee('Et les horaires ?')
        ->assertSeeInOrder(['Sophie Renard', 'Très bien, merci.'])
        ->assertSee('Parfait.');
});

it('marks the comments of a survey in the feedback list, with the rating given', function (): void {
    $response = FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $this->campaign->id, 'rating' => 2]);
    FeedbackEntry::factory()->anonymous()->create([
        'feedback_campaign_response_id' => $response->id,
        'feedback_theme_id' => FeedbackTheme::query()->firstOrFail()->id,
        'body' => 'Trop de monde par table.',
    ]);

    Livewire::actingAs($this->seat)
        ->test('pages::club-admin.feedback.index')
        ->assertSeeInOrder(['Enquête 2026-2027', '2/5', 'Trop de monde par table.']);
});
