<?php

namespace Tests\Feature\Team;

use App\Models\User;
use Tests\TestCase;

class ViewTeamsTest extends TestCase
{
    public function test_unlogged_user_cant_see_teams_index(): void
    {
        $response = $this->get(route('teams.index'));

        $response->assertRedirect('/login');
    }

    public function test_logged_user_can_see_teams_index(): void
    {
        $user = User::find(1);
        $response = $this->actingAs($user)
                        ->get(route('teams.index'));

        $response->assertStatus(200);
    }
}
