<?php

declare(strict_types=1);
use App\Models\Club;
use App\Models\Interclub;

uses(\Tests\Trait\CreateInterclub::class);

uses(\Tests\Trait\CreateUser::class);

test('admin or comitte member can create interclub', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->get(route('interclubs.create'))
        ->assertOK()
        ->assertViewIs('admin.interclubs.create');

    $committee_member = $this->createFakeCommitteeMember();

    $this->actingAs($committee_member)
        ->get(route('interclubs.create'))
        ->assertOK()
        ->assertViewIs('admin.interclubs.create');
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
        ->get(route('interclubs.index'))
        ->assertViewIs('admin.interclubs.index')
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
