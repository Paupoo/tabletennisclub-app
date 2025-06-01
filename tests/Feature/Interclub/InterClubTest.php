<?php

declare(strict_types=1);

namespace Tests\Feature\Interclub;

use App\Models\Club;
use App\Models\Interclub;
use Tests\TestCase;
use Tests\Trait\CreateInterclub;
use Tests\Trait\CreateUser;

class InterClubTest extends TestCase
{
    use CreateInterclub;
    use CreateUser;

    public function test_admin_or_comitte_member_can_create_interclub(): void
    {
        $admin = $this->createFakeAdmin();

        $this->actingAs($admin)
            ->get(route('interclubs.create'))
            ->assertOK()
            ->assertViewIs('admin.interclubs.create');

        $comittee_member = $this->createFakeComitteeMember();

        $this->actingAs($comittee_member)
            ->get(route('interclubs.create'))
            ->assertOK()
            ->assertViewIs('admin.interclubs.create');
    }

    public function test_admin_or_comittee_member_can_store_interclub(): void
    {
        $admin = $this->createFakeAdmin();

        $totalInterclubs = Interclub::count();

        $this->actingAs($admin)
            ->from(route('interclubs.create'))
            ->post(route('interclubs.store'), $this->getValidInterclub())
            ->assertRedirect(route('interclubs.index'))
            ->assertSessionHas('success', 'The match has been added.');

        $this->assertTrue($totalInterclubs + 1 === Interclub::count());

        $comittee_member = $this->createFakeComitteeMember();

        $this->actingAs($comittee_member)
            ->from(route('interclubs.create'))
            ->post(route('interclubs.store'), $this->getValidInterclub())
            ->assertRedirect(route('interclubs.index'))
            ->assertSessionHas('success', 'The match has been added.');
    }

    public function test_captains_are_able_to_create_an_interclub(): void
    {
        // to do
    }

    public function test_captains_are_able_to_store_an_interclub(): void
    {
        // to do
    }

    public function test_invalid_request(): void
    {
        // to do
    }

    public function test_route_index(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('interclubs.index'))
            ->assertViewIs('admin.interclubs.index')
            ->assertOk();
    }

    public function test_storing_interclub_in_the_club_stores_club_address_and_the_room_id(): void
    {
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
    }

    public function test_storing_interclub_not_in_the_club_stores_opposite_club_address(): void
    {
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
    }

    public function test_unlogged_user_cant_access_create_view(): void
    {
        $this->get(route('interclubs.create'))
            ->assertRedirect('/login');
    }

    public function test_unlogged_user_cant_access_index_view(): void
    {
        $this->get(route('interclubs.index'))
            ->assertRedirect('/login');
    }

    public function test_user_cant_create_interclub(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('interclubs.create'))
            ->assertStatus(403);
    }

    public function test_user_cant_store_interclub(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->post(route('interclubs.store'), $this->getValidInterclub())
            ->assertStatus(403);
    }
}
