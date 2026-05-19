<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\Interclub;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
use Tests\Trait\CreateInterclub;
use Tests\Trait\CreateUser;

uses(CreateInterclub::class);

uses(CreateUser::class);

beforeEach(function (): void {
    $season = Season::factory()->create(['is_active' => true]);
    $league = League::create([
        'division' => '1A',
        'level' => 'PROVINCIAL_BW',
        'category' => 'MEN',
        'season_id' => $season->id,
    ]);
    $ourClub = Club::factory()->create(['licence' => config('app.club_licence')]);
    Club::factory()->create();
    $room = Room::factory()->create([
        'capacity_for_interclubs' => 2,
        'street' => $ourClub->street,
        'city_code' => $ourClub->city_code,
        'city_name' => $ourClub->city_name,
    ]);
    Team::create([
        'name' => 'A',
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $ourClub->id,
    ]);
});

test('admin or comitte member can create interclub', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->get(route('admin.interclubs.control-center'))
        ->assertOK();

    $committee_member = $this->createFakeCommitteeMember();

    $this->actingAs($committee_member)
        ->get(route('admin.interclubs.control-center'))
        ->assertOK();
});
test('admin or committee member can store interclub', function (): void {
    $admin = $this->createFakeAdmin();

    $totalInterclubs = Interclub::count();

    $this->actingAs($admin)
        ->from(route('interclubs.create'))
        ->post(route('interclubs.store'), $this->getValidInterclub())
        ->assertRedirect(route('interclubs.index'))
        ->assertSessionHas('success', 'The match has been added.');

    expect($totalInterclubs + 1 === Interclub::count())->toBeTrue();

    $committee_member = $this->createFakeCommitteeMember();

    $this->actingAs($committee_member)
        ->from(route('interclubs.create'))
        ->post(route('interclubs.store'), $this->getValidInterclub())
        ->assertRedirect(route('interclubs.index'))
        ->assertSessionHas('success', 'The match has been added.');
});
test('captains are able to create an interclub', function (): void {
    // to do
})->todo();
test('captains are able to store an interclub', function (): void {
    // to do
})->todo();
test('invalid request', function (): void {
    // to do
})->todo();
test('route index', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('admin.interclubs.control-center'))
        ->assertOk();
});
test('storing interclub in the club stores club address and the room id', function (): void {
    $club = Club::firstWhere('licence', config('app.club_licence'));
    $clubAddress = $club->street . ', ' . $club->city_code . ' ' . $club->city_name;

    $admin = $this->createFakeAdmin();

    $response = $this->actingAs($admin)
        ->post(route('interclubs.store'), $this->getValidInterclubInTheClub())
        ->assertStatus(302);

    $this->assertDatabaseHas('interclubs', [
        'address' => $clubAddress,
        'room_id' => $this->getValidInterclubInTheClub()['room_id'],
    ]);
});
test('storing interclub not in the club stores opposite club address', function (): void {
    $oppositeClub = Club::find($this->getValidInterclubNotInTheClub()['opposite_club_id']);

    $oppositeClubAddress = $oppositeClub->street . ', ' . $oppositeClub->city_code . ' ' . $oppositeClub->city_name;

    $admin = $this->createFakeAdmin();

    $response = $this->actingAs($admin)
        ->post(route('interclubs.store'), $this->getValidInterclubNotInTheClub())
        ->assertStatus(302);

    $this->assertDatabaseHas('interclubs', [
        'address' => $oppositeClubAddress,
        'room_id' => null,
    ]);
});
test('unlogged user cant access create view', function (): void {
    $this->get(route('interclubs.create'))
        ->assertRedirect('/login');
});
test('unlogged user cant access index view', function (): void {
    $this->get(route('interclubs.index'))
        ->assertRedirect('/login');
});
test('user cant create interclub', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('interclubs.create'))
        ->assertStatus(403);
});
test('user cant store interclub', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->post(route('interclubs.store'), $this->getValidInterclub())
        ->assertStatus(403);
});
