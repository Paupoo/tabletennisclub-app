<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'users', 'subscriptions');

/*
 * `JO` and `LR` are how the federation records what a member is affiliated as.
 * The club has its own answer to the same question — `is_competitive` on the
 * season subscription — and the two are allowed to disagree: a member may take
 * up competition, or stop. What is not allowed is for the disagreement to go
 * unnoticed by the person accepting the affiliation.
 */

beforeEach(function (): void {
    $this->season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
});

describe('what the federation says a member plays', function (): void {

    it('offers the competitive formula to a member the federation lists as a player', function (): void {
        $member = User::factory()->create([
            'birthdate' => now()->subYears(25),
            'federation_licence_type' => 'JO',
        ]);

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
            ->assertSet("registrations.{$member->id}.formula", 'competitive');
    });

    it('offers the recreational formula to a member the federation lists as a recreant', function (): void {
        $member = User::factory()->create([
            'birthdate' => now()->subYears(25),
            'federation_licence_type' => 'LR',
        ]);

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
            ->assertSet("registrations.{$member->id}.formula", 'recreative');
    });

    /*
     * A suggestion, never a decision: the member is the one who says what they
     * intend to play this season.
     */
    it('lets the member contradict the federation', function (): void {
        $member = User::factory()->create([
            'birthdate' => now()->subYears(25),
            'federation_licence_type' => 'JO',
        ]);

        Livewire::actingAs($member)
            ->test('pages::club-admin.users.user-space.registration-management', ['user' => $member])
            ->set("registrations.{$member->id}.formula", 'recreative')
            ->call('confirmAffiliation', $member->id);

        expect(Subscription::where('user_id', $member->id)->first()->is_competitive)->toBeFalse();
    });
});

describe('a request that contradicts the federation', function (): void {

    it('tells the committee member reviewing it, without standing in their way', function (): void {
        actingAs(User::factory()->withRole(Role::MEMBERS)->create());

        $member = User::factory()->create([
            'licence' => '166036',
            'ranking' => 'C2',
            'federation_licence_type' => 'JO',
            'federation_synced_at' => now()->subMonths(2),
        ]);

        $request = Subscription::factory()->for($member)->create([
            'season_id' => $this->season->id,
            'status' => 'pending',
            'is_competitive' => false,
        ]);

        Livewire::test('pages::club-admin.users.registrations')
            ->call('review', $request->id)
            ->assertSee(__('The member asked for :formula. Federation: :type on :date', [
                'formula' => __('Recreational'),
                'type' => 'JO',
                'date' => now()->subMonths(2)->format('d/m/Y'),
            ]));
    });

    /*
     * The same disagreement, readable where the club edits its own answer.
     */
    it('flags it on the season roster too', function (): void {
        actingAs(User::factory()->withRole(Role::MEMBERS)->create());

        $member = User::factory()->create([
            'licence' => '166038',
            'ranking' => 'C2',
            'federation_licence_type' => 'JO',
            'federation_synced_at' => now()->subMonths(2),
        ]);

        Subscription::factory()->for($member)->create([
            'season_id' => $this->season->id,
            'status' => 'confirmed',
            'is_competitive' => false,
        ]);

        Livewire::test('pages::club-admin.subscriptions.roster')
            ->assertSee(__('Federation: :type on :date', [
                'type' => 'JO',
                'date' => now()->subMonths(2)->format('d/m/Y'),
            ]));
    });

    it('says nothing when the member and the federation agree', function (): void {
        actingAs(User::factory()->withRole(Role::MEMBERS)->create());

        $member = User::factory()->create([
            'licence' => '166037',
            'ranking' => 'C2',
            'federation_licence_type' => 'JO',
            'federation_synced_at' => now()->subMonths(2),
        ]);

        $request = Subscription::factory()->for($member)->create([
            'season_id' => $this->season->id,
            'status' => 'pending',
            'is_competitive' => true,
        ]);

        Livewire::test('pages::club-admin.users.registrations')
            ->call('review', $request->id)
            ->assertDontSee(__('The member asked for :formula. Federation: :type on :date', [
                'formula' => __('Competitive'),
                'type' => 'JO',
                'date' => now()->subMonths(2)->format('d/m/Y'),
            ]));
    });
});
