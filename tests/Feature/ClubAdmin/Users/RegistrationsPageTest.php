<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations', 'smoke');

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
    actingAs(User::factory()->isAdmin()->create());
});

it('renders the registrations page', function (): void {
    Subscription::factory()->create([
        'season_id' => $this->season->id,
        'status' => 'confirmed',
    ]);

    get(route('admin.users.registrations'))->assertSuccessful();
});

it('renders when a subscription belongs to a soft-deleted user', function (): void {
    $member = User::factory()->create();
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
    ]);

    $member->delete();

    get(route('admin.users.registrations'))
        ->assertSuccessful()
        ->assertSee($member->first_name);
});

it('renders the pending sections when pending requests belong to a soft-deleted user', function (): void {
    $member = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);

    $trainingMember = User::factory()->create();
    $trainingSubscription = Subscription::factory()->create([
        'user_id' => $trainingMember->id,
        'season_id' => $this->season->id,
        'status' => 'paid',
    ]);
    $pack = TrainingPack::factory()->create(['price' => 90]);
    $trainingSubscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    $member->delete();
    $trainingMember->delete();

    get(route('admin.users.registrations'))
        ->assertSuccessful()
        ->assertSee($member->first_name)
        ->assertSee($trainingMember->first_name);
});

it('shows the training of a cancelled affiliation as cancelled, not pending', function (): void {
    $subscription = Subscription::factory()->create([
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);
    $pack = TrainingPack::factory()->create(['name' => 'Récréatif du lundi', 'price' => 90]);
    $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

    // Legacy data: the affiliation was cancelled but the pivot was left pending
    // (bypasses the cascade, which is what this display fix must also rescue).
    $subscription->update(['status' => 'cancelled']);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('statusFilter', 'cancelled')
        ->set('currentRequestId', $subscription->id)
        ->assertSee($pack->name)
        ->assertSee(__('Cancelled'))
        ->assertDontSee(__('Awaiting validation'));
});
