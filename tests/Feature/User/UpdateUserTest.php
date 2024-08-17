<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class UpdateUserTest extends TestCase
{
    use CreateUser;

    public function test_unlogged_user_cannot_access_members_edit(): void
    {
        $this->get(route('users.edit', 1))
            ->assertRedirect('/login');
}

    public function test_member_cannot_access_edit_member_page(): void   
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('users.edit', 1))
            ->assertStatus(403);
    }

    public function test_admin_and_comittee_members_can_access_edit_member_page(): void   
    {
        $admin = $this->createFakeAdmin();
        $comittee_member = $this->createFakeComitteeMember();
        $user = $this->createFakeUser();

        $this->actingAs($admin)
            ->get(route('users.edit', $user))
            ->assertOK();

        $this->actingAs($comittee_member)
            ->get(route('users.edit', $user))
            ->assertOK();
    }

    public function test_members_cant_access_edit_member_page(): void   
    {
        
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->get(route('users.edit', $user))
            ->assertStatus(403);

        $this->actingAs($user)
            ->get(route('users.edit', $user))
            ->assertStatus(403);
    }

    public function test_member_update_doesnt_change_entries_in_the_database(): void
    {
        $admin = $this->createFakeAdmin();
        $user = $this->createFakeUser();

        $totalUsers = User::count();

        $this->actingAs($admin)
            ->from(route('users.edit', $user))
            ->put(route('users.update', $user), [
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
            ->assertRedirect(route('users.index'))
            ->assertSessionDoesntHaveErrors([
            ])
            ->assertSessionHas([
                'success',
            ]);

        $this->assertDatabaseCount('users', $totalUsers);
    }

    public function test_member_update_with_invalid_data_is_returning_errors(): void
    {
        $admin = $this->createFakeAdmin();
        $user = $this->createFakeUser();

        $this->actingAs($admin)
            ->from(route('users.edit', $user))
            ->put(route('users.update', $user), [
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
            ->assertRedirect(route('users.edit', $user))
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
        $admin = $this->createFakeAdmin();
        $user = $this->createFakeUser();

        $this->actingAs($admin)
            ->from(route('users.edit', $user))
            ->put(route('users.update', $user), [
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
            ->assertRedirect(route('users.edit', $user))
            ->assertSessionHasErrors([
                'licence',
                'ranking',
            ]);
    }

    public function test_member_can_be_casual_with_valid_licence_and_ranking(): void
    {
        $admin = $this->createFakeAdmin();
        $user = $this->createFakeUser();

        $this->actingAs($admin)
            ->from(route('users.edit', $user))
            ->put(route('users.update', $user), [
                'first_name' => 'Jean',
                'last_name' => 'Lechat',
                'sex' => 'MEN',
                'email' => 'jean.lechat@gmail.com',
                'password' => 'Jean1234!',
                'password_confirmation' => 'Jean1234!',
                'is_admin' => false,
                'is_comittee_member' => false,
                'licence' => '124599',
                'ranking' => 'B0',
            ])
            ->assertValid()
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();
    }

    public function test_member_can_be_casual_with_no_ranking_and_no_licence(): void
    {
        $admin = $this->createFakeAdmin();
        $user = $this->createFakeUser();

        $this->actingAs($admin)
            ->from(route('users.edit', $user))
            ->put(route('users.update', $user), [
                'first_name' => 'Jean',
                'last_name' => 'Lechat',
                'sex' => 'MEN',
                'email' => 'jean.lechat@gmail.com',
                'password' => 'Jean1234!',
                'password_confirmation' => 'Jean1234!',
                'is_admin' => false,
                'is_comittee_member' => false,
                'ranking' => 'NA',
            ])
            ->assertValid()
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();
    }
}
