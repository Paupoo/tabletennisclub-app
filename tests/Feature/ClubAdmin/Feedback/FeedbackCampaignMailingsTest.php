<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use App\Domains\ClubAdmin\Feedback\Notifications\CampaignInvitationNotification;
use App\Domains\ClubAdmin\Feedback\Notifications\CampaignSummaryNotification;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->season = makeActiveSeason();
});

/**
 * @return array<int, string>
 */
function campaignMailNamesFor(string $address): array
{
    $names = [];

    Notification::assertSentOnDemand(CampaignInvitationNotification::class, function (CampaignInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($address, &$names): bool {
        if ($notifiable->routes['mail'] !== $address) {
            return false;
        }

        $names = $notification->names;

        return true;
    });

    return $names;
}

it('invites every active member on the opening day, one mail per address', function (): void {
    $campaign = FeedbackCampaign::factory()->scheduled()->create(['opens_on' => today(), 'closes_on' => today()->addWeeks(3)]);
    $parent = activeMember($this->season, ['first_name' => 'Sophie', 'email' => 'sophie@example.test']);
    $ward = activeMember($this->season, ['first_name' => 'Lucas', 'email' => null]);
    $ward->guardians()->attach(Guardian::factory()->create(['user_id' => $parent->id, 'email' => 'sophie@example.test'])->id);
    activeMember($this->season, ['first_name' => 'Marc', 'email' => 'marc@example.test']);
    User::factory()->create(['email' => 'gone@example.test']);

    $this->artisan('feedback:send-campaign-mailings')->assertSuccessful();

    Notification::assertSentOnDemandTimes(CampaignInvitationNotification::class, 2);
    expect(campaignMailNamesFor('sophie@example.test'))->toEqualCanonicalizing(['Sophie', 'Lucas'])
        ->and(campaignMailNamesFor('marc@example.test'))->toBe(['Marc'])
        ->and($campaign->fresh()->invited_at)->not->toBeNull();
});

it('invites only once', function (): void {
    FeedbackCampaign::factory()->scheduled()->create(['opens_on' => today(), 'closes_on' => today()->addWeeks(3)]);
    activeMember($this->season);

    $this->artisan('feedback:send-campaign-mailings');
    $this->artisan('feedback:send-campaign-mailings');

    Notification::assertSentOnDemandTimes(CampaignInvitationNotification::class, 1);
});

it('sends nothing for a draft', function (): void {
    FeedbackCampaign::factory()->create(['opens_on' => today(), 'closes_on' => today()->addWeeks(3)]);
    activeMember($this->season);

    $this->artisan('feedback:send-campaign-mailings');

    Notification::assertNothingSent();
});

it('reminds once, halfway, those who have not answered', function (): void {
    $campaign = FeedbackCampaign::factory()->scheduled()->create([
        'opens_on' => today()->subDays(10),
        'closes_on' => today()->addDays(10),
        'invited_at' => now()->subDays(10),
    ]);
    $answered = activeMember($this->season, ['email' => 'done@example.test']);
    activeMember($this->season, ['first_name' => 'Marc', 'email' => 'marc@example.test']);
    $campaign->participants()->attach($answered->id);

    $this->artisan('feedback:send-campaign-mailings');
    $this->artisan('feedback:send-campaign-mailings');

    Notification::assertSentOnDemandTimes(CampaignInvitationNotification::class, 1);
    expect(campaignMailNamesFor('marc@example.test'))->toBe(['Marc'])
        ->and($campaign->fresh()->reminded_at)->not->toBeNull();
});

it('does not remind before halfway', function (): void {
    FeedbackCampaign::factory()->scheduled()->create([
        'opens_on' => today()->subDays(2),
        'closes_on' => today()->addDays(18),
        'invited_at' => now()->subDays(2),
    ]);
    activeMember($this->season);

    $this->artisan('feedback:send-campaign-mailings');

    Notification::assertNothingSent();
});

it('sends the committee a summary the day after the close', function (): void {
    $campaign = FeedbackCampaign::factory()->scheduled()->create([
        'opens_on' => today()->subWeeks(3),
        'closes_on' => today()->subDay(),
        'invited_at' => now()->subWeeks(3),
        'reminded_at' => now()->subWeeks(2),
    ]);
    FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $campaign->id, 'rating' => 4]);
    FeedbackCampaignResponse::factory()->create(['feedback_campaign_id' => $campaign->id, 'rating' => 5]);
    $seat = User::factory()->isCommitteeMember()->create();

    $this->artisan('feedback:send-campaign-mailings');
    $this->artisan('feedback:send-campaign-mailings');

    Notification::assertSentToTimes($seat, CampaignSummaryNotification::class, 1);
    Notification::assertSentTo($seat, CampaignSummaryNotification::class, fn (CampaignSummaryNotification $notification): bool => str_contains(implode(' ', $notification->toMail($seat)->introLines), '4,5'));
});

it('runs every morning', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'feedback:send-campaign-mailings'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 9 * * *');
});
