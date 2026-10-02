<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;

pest()->group('club-admin', 'users');

/*
| A responsible adult's file names the members they answer for — the mirror of
| the "Responsible adults" block on a child's file.
*/

beforeEach(function (): void {
    $this->season = makeActiveSeason();
    $this->previous = Season::factory()->create([
        'name' => 'previous-season',
        'start_at' => now()->subYear()->startOfYear(),
        'end_at' => now()->subYear()->endOfYear(),
    ]);

    $this->reader = User::factory()->isCommitteeMember()->create();
    $this->parent = User::factory()->create();

    // Two wards: a fixture of one would let a lazy load pass unseen.
    $this->player = User::factory()->minor()->create([
        'first_name' => 'Lou',
        'last_name' => 'Pupille-Joueuse',
        'birthdate' => now()->subYears(12)->subMonth(),
    ]);
    Subscription::factory()->for($this->player)->for($this->season)->create(['status' => 'confirmed', 'is_competitive' => true]);

    $this->lapsed = User::factory()->minor()->create(['first_name' => 'Tom', 'last_name' => 'Pupille-Absent']);
    Subscription::factory()->for($this->lapsed)->for($this->previous)->create(['status' => 'paid']);

    $guardian = Guardian::factory()->create(['user_id' => $this->parent->id]);
    $guardian->users()->attach([$this->player->id, $this->lapsed->id]);
});

it('lists the wards with their age and where they stand', function (): void {
    $response = $this->actingAs($this->reader)->get(route('admin.users.show', $this->parent))->assertOk();

    $response->assertSee(__('Responsible for'))
        ->assertSee('Pupille-Joueuse')
        ->assertSee('Pupille-Absent')
        ->assertSee(__(':age years', ['age' => 12]))
        ->assertSee(route('admin.users.show', $this->player), escape: false)
        ->assertSee('data-membership-status="new"', escape: false)
        ->assertSee('data-licence="competitive"', escape: false)
        ->assertSee('data-membership-status="to_follow_up"', escape: false)
        ->assertSee(__('Last season: :season', ['season' => 'previous-season']));
});

it('leaves the block out of a member who answers for nobody', function (): void {
    $this->actingAs($this->reader)
        ->get(route('admin.users.show', $this->player))
        ->assertOk()
        ->assertDontSee(__('Responsible for'));
});

it('shows no licence for an affiliation that was cancelled', function (): void {
    $member = User::factory()->create();
    Subscription::factory()->for($member)->for($this->season)->create(['status' => 'cancelled', 'is_competitive' => false]);

    $this->actingAs($this->reader)
        ->get(route('admin.users.show', $member))
        ->assertOk()
        ->assertSee(__('Cancelled'))
        ->assertDontSee(__('Recreational'));
});

it('lists the wards on the edit form too, linked to their file', function (): void {
    $admin = User::factory()->isAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $this->parent))
        ->assertOk()
        ->assertSee(__('Responsible for'))
        ->assertSee('Pupille-Joueuse')
        ->assertSee('Pupille-Absent')
        ->assertSee(__(':age years', ['age' => 12]))
        ->assertSee(route('admin.users.show', $this->player), escape: false)
        ->assertSee('data-membership-status="to_follow_up"', escape: false);
});

it('leaves the wards block out of the edit form of a member who answers for nobody', function (): void {
    $admin = User::factory()->isAdmin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $this->player))
        ->assertOk()
        ->assertDontSee(__('Responsible for'));
});
