<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * @param  array<int, array{label: string, route: string}>  $alerts
 */
function surveyAlertIn(array $alerts, string $route): bool
{
    return collect($alerts)->contains(fn (array $alert): bool => $alert['route'] === $route);
}

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('asks a member who has not answered the open survey, from the dashboard', function (): void {
    FeedbackCampaign::factory()->open()->create();
    $member = activeMember($this->season);

    $this->actingAs($member)->get(route('dashboard'))
        ->assertViewHas('alerts', fn (array $alerts): bool => surveyAlertIn($alerts, route('admin.user.survey')));
});

it('stops asking once the member answered', function (): void {
    $campaign = FeedbackCampaign::factory()->open()->create();
    $member = activeMember($this->season);
    $campaign->participants()->attach($member->id);

    $this->actingAs($member)->get(route('dashboard'))
        ->assertViewHas('alerts', fn (array $alerts): bool => ! surveyAlertIn($alerts, route('admin.user.survey')));
});

it('does not ask a member who is not affiliated', function (): void {
    FeedbackCampaign::factory()->open()->create();

    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertViewHas('alerts', fn (array $alerts): bool => ! surveyAlertIn($alerts, route('admin.user.survey')));
});

it('points the box to the open survey', function (): void {
    FeedbackCampaign::factory()->open()->create();
    $member = activeMember($this->season);

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.feedback', ['user' => $member])
        ->assertSee(route('admin.user.survey'));
});

it('reminds the délégation, late in the season, that no survey is planned', function (): void {
    $season = Season::current();
    $season->update(['start_at' => today()->subMonths(10), 'end_at' => today()->addMonth()]);
    $delegate = User::factory()->withRole(Role::FEEDBACK)->create();

    $this->actingAs($delegate)->get(route('dashboard'))
        ->assertViewHas('alerts', fn (array $alerts): bool => surveyAlertIn($alerts, route('admin.feedback.campaigns')));

    FeedbackCampaign::factory()->scheduled()->create(['opens_on' => today()->addDay(), 'closes_on' => today()->addWeeks(3)]);

    $this->actingAs($delegate)->get(route('dashboard'))
        ->assertViewHas('alerts', fn (array $alerts): bool => ! surveyAlertIn($alerts, route('admin.feedback.campaigns')));
});

it('does not remind early in the season', function (): void {
    Season::current()->update(['start_at' => today()->subMonth(), 'end_at' => today()->addMonths(10)]);
    $delegate = User::factory()->withRole(Role::FEEDBACK)->create();

    $this->actingAs($delegate)->get(route('dashboard'))
        ->assertViewHas('alerts', fn (array $alerts): bool => ! surveyAlertIn($alerts, route('admin.feedback.campaigns')));
});
