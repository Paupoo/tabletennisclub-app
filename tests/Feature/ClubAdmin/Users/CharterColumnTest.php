<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\CharterSignature;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations');

/*
|--------------------------------------------------------------------------
| Charter column on the committee roster
|--------------------------------------------------------------------------
|
| Affiliations created from the back office carry no signature — the member
| was not at the screen to give one. The committee needs to see the gap to
| chase it, which is the only reason this column exists.
|
*/

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
    actingAs(User::factory()->isAdmin()->create());
});

it('flags an affiliation whose member never signed the charter', function (): void {
    $member = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->assertOk()
        ->assertSee(__('Not signed'));
});

it('marks the affiliation of a member who signed', function (): void {
    $member = User::factory()->create();
    Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);
    CharterSignature::sign($member, $this->season, $member);

    Livewire::test('pages::club-admin.users.registrations')
        ->assertOk()
        ->assertSee(__('Signed'))
        ->assertDontSee(__('Not signed'));
});

it('reads the signatures in one query, whatever the roster size', function (): void {
    $members = User::factory()->count(5)->create();

    foreach ($members as $member) {
        Subscription::factory()->create([
            'user_id' => $member->id,
            'season_id' => $this->season->id,
            'status' => 'pending',
        ]);
        CharterSignature::sign($member, $this->season, $member);
    }

    $queries = 0;
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'charter_signatures')) {
            $queries++;
        }
    });

    Livewire::test('pages::club-admin.users.registrations')->assertOk();

    expect($queries)->toBe(1);
});
