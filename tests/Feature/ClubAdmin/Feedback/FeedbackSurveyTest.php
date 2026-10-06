<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaignResponse;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use App\Domains\ClubAdmin\Feedback\Models\FeedbackTheme;
use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use App\Support\AccountProxy;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

const SURVEY_COMPONENT = 'pages::club-admin.users.user-space.survey';

/**
 * A managed account, active this season, with the given member as guardian.
 */
function surveyWardOf(User $guardianMember, Season $season): User
{
    $ward = activeMember($season, ['email' => null, 'first_name' => 'Lucas']);
    $guardian = Guardian::factory()->create([
        'user_id' => $guardianMember->id,
        'first_name' => $guardianMember->first_name,
        'last_name' => $guardianMember->last_name,
        'email' => $guardianMember->email,
    ]);
    $ward->guardians()->attach($guardian->id);

    return $ward;
}

beforeEach(function (): void {
    Notification::fake();
    $this->season = makeActiveSeason();
    $this->campaign = FeedbackCampaign::factory()->open()->create(['year_question' => 'Que pensez-vous des horaires ?']);
    $this->member = activeMember($this->season, ['first_name' => 'Sophie']);
    $this->trainings = FeedbackTheme::query()->where('name', 'Entraînements')->firstOrFail();
});

it('records a signed answer, its comments per theme and the answer of the year', function (): void {
    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->assertSee('Que pensez-vous des horaires ?')
        ->set('rating', 4)
        ->set("comments.{$this->trainings->id}", 'Le passage à 19h est top.')
        ->set('yearAnswer', 'Très bien.')
        ->call('send')
        ->assertHasNoErrors();

    $response = FeedbackCampaignResponse::sole();
    expect($response->user_id)->toBe($this->member->id)
        ->and($response->rating)->toBe(4)
        ->and($response->year_answer)->toBe('Très bien.')
        ->and($this->campaign->hasAnswered($this->member))->toBeTrue();

    $comment = FeedbackEntry::sole();
    expect($comment->feedback_campaign_response_id)->toBe($response->id)
        ->and($comment->feedback_theme_id)->toBe($this->trainings->id)
        ->and($comment->user_id)->toBe($this->member->id);
});

it('requires the rating', function (): void {
    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->call('send')
        ->assertHasErrors(['rating']);
});

it('lets a signed answer be changed while the survey is open', function (): void {
    $screen = Livewire::actingAs($this->member)->test(SURVEY_COMPONENT)
        ->set('rating', 3)
        ->set("comments.{$this->trainings->id}", 'Premier jet.')
        ->call('send');

    Livewire::actingAs($this->member)->test(SURVEY_COMPONENT)
        ->assertSet('rating', 3)
        ->assertSet("comments.{$this->trainings->id}", 'Premier jet.')
        ->set('rating', 5)
        ->set("comments.{$this->trainings->id}", '')
        ->call('send')
        ->assertHasNoErrors();

    expect(FeedbackCampaignResponse::sole()->rating)->toBe(5)
        ->and(FeedbackEntry::count())->toBe(0);
});

it('keeps an anonymous answer apart from its author, and final', function (): void {
    $this->travelTo(now()->setTime(18, 12, 40));

    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->set('rating', 2)
        ->set("comments.{$this->trainings->id}", 'Trop de monde par table.')
        ->set('anonymous', true)
        ->call('send')
        ->assertHasNoErrors();

    $response = FeedbackCampaignResponse::sole();
    expect($response->user_id)->toBeNull()
        ->and($response->created_at->format('H:i:s'))->toBe('00:00:00')
        ->and(FeedbackEntry::sole()->user_id)->toBeNull()
        ->and(FeedbackEntry::sole()->created_at->format('H:i:s'))->toBe('00:00:00')
        ->and($this->campaign->hasAnswered($this->member))->toBeTrue();

    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->assertSee(__('You already answered this survey, anonymously. Thank you!'))
        ->set('rating', 5)
        ->call('send');

    expect(FeedbackCampaignResponse::count())->toBe(1);
});

it('records an offer of help in the member’s name beside an anonymous answer', function (): void {
    $task = HelpTask::query()->where('name', 'Arbitrer')->firstOrFail();

    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->set('rating', 4)
        ->set('anonymous', true)
        ->set('helpTaskIds', [$task->id])
        ->call('send');

    expect(HelpOffer::sole()->user_id)->toBe($this->member->id)
        ->and(FeedbackCampaignResponse::sole()->user_id)->toBeNull();
});

it('sends no mail for each answer', function (): void {
    $delegate = User::factory()->withRole(Role::FEEDBACK)->create();

    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->set('rating', 4)
        ->set("comments.{$this->trainings->id}", 'Un commentaire.')
        ->call('send');

    Notification::assertNothingSentTo($delegate);
});

it('turns away a member who is not affiliated this season', function (): void {
    $newcomer = User::factory()->create();

    Livewire::actingAs($newcomer)
        ->test(SURVEY_COMPONENT)
        ->assertSee(__('This survey is for the members affiliated this season.'))
        ->set('rating', 4)
        ->call('send');

    expect(FeedbackCampaignResponse::count())->toBe(0);
});

it('says when no survey is open', function (): void {
    $this->campaign->update(['closes_on' => today()->subDay(), 'opens_on' => today()->subWeek()]);

    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->assertSee(__('No survey is open right now.'));
});

it('lets a guardian answer for their ward, without the offer of help', function (): void {
    $ward = surveyWardOf($this->member, $this->season);

    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->assertSee('Lucas')
        ->call('answerFor', $ward->id)
        ->assertRedirect(route('admin.user.survey'));

    expect(AccountProxy::isActing())->toBeTrue();

    $this->actingAs($ward);
    Livewire::actingAs($ward)
        ->test(SURVEY_COMPONENT)
        ->assertDontSee(__('Fancy giving a hand?'))
        ->set('rating', 5)
        ->call('send')
        ->assertHasNoErrors();

    expect(FeedbackCampaignResponse::sole()->user_id)->toBe($ward->id);
});

it('refuses to answer for somebody the member does not answer for', function (): void {
    $stranger = activeMember($this->season, ['email' => null]);

    Livewire::actingAs($this->member)
        ->test(SURVEY_COMPONENT)
        ->call('answerFor', $stranger->id)
        ->assertForbidden();
});

it('previews the form for the délégation without recording anything', function (): void {
    $draft = FeedbackCampaign::factory()->create(['intro' => 'Introduction du brouillon.']);
    $delegate = User::factory()->withRole(Role::FEEDBACK)->create();

    Livewire::actingAs($delegate)
        ->test(SURVEY_COMPONENT, ['campaign' => $draft])
        ->assertSee('Introduction du brouillon.')
        ->assertSee(__('Preview: nothing you fill in is recorded.'))
        ->set('rating', 4)
        ->call('send');

    expect(FeedbackCampaignResponse::count())->toBe(0);
});

it('offers a guardian who is not a member the seat of their only child', function (): void {
    $parent = User::factory()->create(['first_name' => 'Claire']);
    $ward = surveyWardOf($parent, $this->season);

    expect($parent->isGuardianOnlyAccount())->toBeTrue();

    Livewire::actingAs($parent)
        ->test(SURVEY_COMPONENT)
        ->assertSee('Lucas')
        ->call('answerFor', $ward->id)
        ->assertRedirect(route('admin.user.survey'));
});
