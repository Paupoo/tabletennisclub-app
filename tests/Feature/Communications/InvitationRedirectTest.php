<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Trainings\Models\TrainingPack;
use App\Support\AccountProxy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
| The button of an invitation leads here, not to the event itself. A parent
| registers their child from the child's seat, so this page asks "for whom?",
| takes that seat when needed, and hands over to the registration page that
| already exists. The link is the same for every reader, which is what lets a
| copy-pasted message carry it too.
*/

/** A guardian with an account, answering for the given managed children. */
function invitationGuardian(User $account, User ...$wards): void
{
    $record = Guardian::factory()->create(['email' => $account->email, 'user_id' => $account->id]);

    foreach ($wards as $ward) {
        $ward->guardians()->attach($record);
    }
}

function invitationWard(string $firstName): User
{
    return User::factory()->create(['email' => null, 'first_name' => $firstName, 'birthdate' => now()->subYears(10)]);
}

it('asks a visitor to sign in first', function (): void {
    $tournament = Tournament::factory()->create();

    get(route('communications.invitation', ['tournament', $tournament->id]))->assertRedirect(route('login'));
});

it('sends a member who answers only for themself straight to the registrations', function (): void {
    $member = User::factory()->create();
    $tournament = Tournament::factory()->create();
    actingAs($member);

    get(route('communications.invitation', ['tournament', $tournament->id]))
        ->assertRedirect(route('admin.user.event-subscription', $member));
});

it('sends a training pack invitation to the season page', function (): void {
    $member = User::factory()->create();
    $pack = TrainingPack::factory()->create();
    actingAs($member);

    get(route('communications.invitation', ['training_pack', $pack->id]))
        ->assertRedirect(route('admin.user.registration-management', $member));
});

it('takes the seat of the only child a parent answers for', function (): void {
    $parent = User::factory()->create();
    $child = invitationWard('Léa');
    invitationGuardian($parent, $child);
    $tournament = Tournament::factory()->create();
    actingAs($parent);

    get(route('communications.invitation', ['tournament', $tournament->id]))
        ->assertRedirect(route('admin.user.event-subscription', $child));

    expect(auth()->id())->toBe($child->id)
        ->and(AccountProxy::origin()?->id)->toBe($parent->id);
});

it('asks a parent who plays too for whom they are registering', function (): void {
    $parent = User::factory()->create();
    Subscription::factory()->create(['user_id' => $parent->id, 'season_id' => Season::factory()->create(['is_active' => true])->id]);
    invitationGuardian($parent, invitationWard('Léa'), invitationWard('Tom'));
    $tournament = Tournament::factory()->create();
    actingAs($parent);

    get(route('communications.invitation', ['tournament', $tournament->id]))
        ->assertOk()
        ->assertSee(__('For whom?'))
        ->assertSee('Léa')
        ->assertSee('Tom')
        ->assertSee($parent->first_name);
});

it('takes the chosen child seat, then hands over', function (): void {
    $parent = User::factory()->create();
    $child = invitationWard('Léa');
    invitationGuardian($parent, $child, invitationWard('Tom'));
    $tournament = Tournament::factory()->create();
    actingAs($parent);

    post(route('communications.invitation.choose', ['tournament', $tournament->id]), ['user_id' => $child->id])
        ->assertRedirect(route('admin.user.event-subscription', $child));

    expect(auth()->id())->toBe($child->id)
        ->and(AccountProxy::origin()?->id)->toBe($parent->id);
});

it('gives the seat back when the parent chooses themself', function (): void {
    $parent = User::factory()->create();
    Subscription::factory()->create(['user_id' => $parent->id, 'season_id' => Season::factory()->create(['is_active' => true])->id]);
    $child = invitationWard('Léa');
    invitationGuardian($parent, $child);
    $tournament = Tournament::factory()->create();
    actingAs($parent);
    AccountProxy::start($child);

    post(route('communications.invitation.choose', ['tournament', $tournament->id]), ['user_id' => $parent->id])
        ->assertRedirect(route('admin.user.event-subscription', $parent));

    expect(auth()->id())->toBe($parent->id)
        ->and(AccountProxy::isActing())->toBeFalse();
});

it('refuses a seat the visitor does not hold', function (): void {
    $stranger = invitationWard('Stranger');
    $tournament = Tournament::factory()->create();
    actingAs(User::factory()->create());

    post(route('communications.invitation.choose', ['tournament', $tournament->id]), ['user_id' => $stranger->id])
        ->assertForbidden();
});

it('knows nothing of other invitation kinds', function (): void {
    actingAs(User::factory()->create());

    get('/invitation/bar/1')->assertNotFound();
});
