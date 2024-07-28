<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UpdateUserTest extends TestCase
{

    public function test_unlogged_user_cannot_access_members_edit(): void
    {
        $this->get(route('members.edit', 1))
            ->assertRedirect('/login');
}

    public function test_member_cannot_access_edit_member_page(): void   
    {
        $member = $this->createMemberUser();

        $this->actingAs($member)
            ->get(route('members.edit', 1))
            ->assertStatus(403);
    }

    public function test_admin_and_comittee_members_can_access_edit_member_page(): void   
    {
        $admin = $this->createMemberUser('is_admin');
        $comittee_member = $this->createMemberUser('is_comittee_member');
        $member = $this->createMemberUser();

        $this->actingAs($admin)
            ->get(route('members.edit', $member->id))
            ->assertOK();

        $this->actingAs($comittee_member)
            ->get(route('members.edit', $member->id))
            ->assertOK();
    }

    public function test_members_cant_access_edit_member_page(): void   
    {
        
        $member = $this->createMemberUser();

        $this->actingAs($member)
            ->get(route('members.edit', $member->id))
            ->assertStatus(403);

        $this->actingAs($member)
            ->get(route('members.edit', $member->id))
            ->assertStatus(403);
    }

    public function test_member_update_doesnt_change_entries_in_the_database(): void
    {
        $admin = $this->createMemberUser('is_admin');
        $member = $this->createMemberUser();

        $totalUsers = User::count();

        $this->actingAs($admin)
            ->from(route('members.edit', $member->id))
            ->put(route('members.update', $member->id), [
                'first_name' => 'Jean',
                'last_name' => 'Lechat',
                'sex' => 'MEN',
                'email' => 'jean.lechat@gmail.com',
                'password' => 'Jean1234!',
                'password_confirmation' => 'Jean1234!',
                'is_admin' => false,
                'is_comittee_member' => false,
                'is_competitor' => false,
                'licence' => '111952',
                'ranking' => 'E4',
            ])
            ->assertValid()
            ->assertRedirect(route('members.index'))
            ->assertSessionDoesntHaveErrors([
            ])
            ->assertSessionHas([
                'success',
            ]);

        $this->assertDatabaseCount('users', $totalUsers);
    }

    public function test_member_update_with_invalid_data_is_returning_errors(): void
    {
        $admin = $this->createMemberUser('is_admin');
        $member = $this->createMemberUser();

        $this->actingAs($admin)
            ->from(route('members.edit', $member->id))
            ->put(route('members.update', $member->id), [
                'first_name' => 'Lorem ipsum dolor sit, amet consectetur adipisicing elit. Aliquid dolorem officia est laborum facilis illum reprehenderit suscipit excepturi! Labore nesciunt, atque iure pariatur inventore quia fuga ex amet esse fugiat.
                Autem dicta nostrum laudantium architecto sapiente obcaecati nemo, consequuntur placeat adipisci minima sit maiores dignissimos vel dolores fugit eum at earum repellat illo eius modi? Amet officiis incidunt facere illo!
                Repellat saepe ratione veritatis maxime vel incidunt magni praesentium! Modi quod ullam doloremque, aliquid, rem maxime tempore quasi possimus quia corporis beatae eligendi natus ut? Explicabo earum harum ipsa animi?
                Quas vitae odio, possimus eius rem aliquid odit praesentium. Recusandae, fugit cupiditate, totam minima, voluptatem ea officia accusamus unde possimus hic aliquid illo excepturi dolorem impedit sed vitae a aut.',
                'last_name' => 1,
                'sex' => 'femme',
                'email' => 'test.com',
                'is_active' => 15,
                'is_admin' => 'false',
                'is_comittee_member' => null,
                'licence' => '114399',
                'ranking' => 'E5',
            ])
            ->assertInvalid([
                'first_name',
                'last_name',
                'sex',
                'email',
                'licence',
                'ranking',
            ])
            ->assertRedirect(route('members.edit', $member->id))
            ->assertSessionHasErrors([
                'first_name',
                'last_name',
                'sex',
                'email',
                'licence',
                'ranking',
            ]);
    }

    public function test_member_cant_be_competitor_without_valid_licence_and_ranking(): void
    {
        $admin = $this->createMemberUser('is_admin');
        $member = $this->createMemberUser();

        $this->actingAs($admin)
            ->from(route('members.edit', $member->id))
            ->put(route('members.update', $member->id), [
                'first_name' => 'Jean',
                'last_name' => 'Lechat',
                'sex' => 'MEN',
                'email' => 'jean.lechat@gmail.com',
                'password' => 'Jean1234!',
                'password_confirmation' => 'Jean1234!',
                'is_admin' => false,
                'is_comittee_member' => false,
                'is_competitor' => true,
                'licence' => '1245',
                'ranking' => 'NA',
            ])
            ->assertInvalid([
                'licence',
                'ranking',
            ])
            ->assertRedirect(route('members.edit', $member->id))
            ->assertSessionHasErrors([
                'licence',
                'ranking',
            ]);
    }

    public function test_member_can_be_casual_with_valid_licence_and_ranking(): void
    {
        $admin = $this->createMemberUser('is_admin');
        $member = $this->createMemberUser();

        $this->actingAs($admin)
            ->from(route('members.edit', $member->id))
            ->put(route('members.update', $member->id), [
                'first_name' => 'Jean',
                'last_name' => 'Lechat',
                'sex' => 'MEN',
                'email' => 'jean.lechat@gmail.com',
                'password' => 'Jean1234!',
                'password_confirmation' => 'Jean1234!',
                'is_admin' => false,
                'is_comittee_member' => false,
                'is_competitor' => false,
                'licence' => '124599',
                'ranking' => 'B0',
            ])
            ->assertValid()
            ->assertRedirect(route('members.index'))
            ->assertSessionHasNoErrors();
    }

    /**
     * Create a member and associate a role (Member by default)
     *
     * @param string $role
     * @return Model
     */
    private function createMemberUser(string $role = ''): Model
    {        
        $user = User::factory()->create();
        
        if ($role === 'is_admin') {
            $user->is_admin = true;
        } elseif  ($role === 'is_comittee_member') {
            $user->is_comittee_member = true;
        }

        return $user;
    }

}
