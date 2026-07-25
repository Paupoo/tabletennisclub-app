<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\FamilyGroup;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingLevel;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Issue #53 — clarify the licence vs training distinction on the
| registration page, show pack schedules, label the trainer explicitly.
|--------------------------------------------------------------------------
*/

function makeOpenSeason(): Season
{
    return Season::factory()->create([
        'is_active' => true,
        'registrations_open' => true,
        'start_at' => now()->startOfYear(),
        'end_at' => now()->endOfYear(),
    ]);
}

it('explains the licence vs training distinction when registering', function (): void {
    makeOpenSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee(__('How does it work?'))
        ->assertSee(__('Your licence'))
        ->assertSee(__('Directed training'))
        ->assertSee(__('Official interclub matches and AFTT ranking.'))
        ->assertDontSee(__('Official matches, ranking, and advanced coaching.'));
});

it('shows the pack schedule, room, level and labelled trainer name', function (): void {
    $season = makeOpenSeason();
    $trainer = User::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);
    $room = Room::factory()->create(['name' => 'Salle Blocry']);
    TrainingPack::factory()->create([
        'season_id' => $season->id,
        'name' => 'Perfectionnement',
        'trainer_id' => $trainer->id,
        'room_id' => $room->id,
        'level' => TrainingLevel::ELITE->value,
        'day_of_week' => 2,
        'start_time' => '20:30:00',
        'duration_minutes' => 90,
        'is_active' => true,
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee(__('Schedule'))
        ->assertSee('Mardi · 20h30 – 22h00')
        ->assertSee(__('Room'))
        ->assertSee('Salle Blocry')
        ->assertSee(__('Level'))
        ->assertSee(__('Trainer'))
        ->assertSee('Jean Dupont');
});

it('hides the trainer row for packs without a trainer', function (): void {
    $season = makeOpenSeason();
    TrainingPack::factory()->create([
        'season_id' => $season->id,
        'name' => 'Entrée libre',
        'trainer_id' => null,
        'day_of_week' => 1,
        'start_time' => '20:00:00',
        'duration_minutes' => 150,
        'is_active' => true,
        'is_open_enrollment' => true,
    ]);
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee('Entrée libre')
        ->assertDontSee(__('Trainer'));
});

it('hides the family selector for a member without family', function (): void {
    makeOpenSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertDontSee(__('Your family'))
        ->assertSee(__('To add a family member, please contact the committee.'));
});

it('shows a labelled member card per family member with their registration status', function (): void {
    makeOpenSeason();
    $user = User::factory()->create(['first_name' => 'Aurélien', 'last_name' => 'Paulus']);
    $child = User::factory()->create(['first_name' => 'Lucas', 'last_name' => 'Paulus']);
    $group = FamilyGroup::factory()->create();
    $group->users()->attach([$user->id, $child->id]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee(__('Your family'))
        ->assertSee(__('Select the person to manage'))
        ->assertSee('Aurélien Paulus')
        ->assertSee('Lucas Paulus')
        ->assertSee(__('To register'))
        ->assertDontSee(__('To add a family member, please contact the committee.'));
});

it('offers the directed-training interest toggle below the packs when registering', function (): void {
    makeOpenSeason();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee(__('No slot suits you? Let us know you are interested in directed training'))
        ->assertDontSee(__('I would like directed training (with a coach)'));
});

/*
|--------------------------------------------------------------------------
| Issue #43 — once submitted, the member must still see which licence they
| asked for: the choice is theirs, and it decides what they will be billed.
|--------------------------------------------------------------------------
*/

it('shows the chosen licence on a submitted registration', function (string $status, bool $isCompetitive, string $expected): void {
    $season = makeOpenSeason();
    $user = User::factory()->create();
    Subscription::factory()->for($user)->create([
        'season_id' => $season->id,
        'status' => $status,
        'is_competitive' => $isCompetitive,
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.registration-management', ['user' => $user])
        ->assertSee(__($expected));
})->with([
    'pending competition' => ['pending', true, 'Competition licence'],
    'pending recreational' => ['pending', false, 'Recreational licence'],
    'confirmed competition' => ['confirmed', true, 'Competition licence'],
    'confirmed recreational' => ['confirmed', false, 'Recreational licence'],
]);
