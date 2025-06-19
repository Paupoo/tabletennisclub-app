<?php

declare(strict_types=1);
use App\Models\User;

uses(\Tests\Trait\CreateUser::class);

test('admin and committee members can access edit member page', function (): void {
    $admin = $this->createFakeAdmin();
    $committee_member = $this->createFakeCommitteeMember();
    $user = $this->createFakeUser();

    $this->actingAs($admin)
        ->get(route('users.edit', $user))
        ->assertOK();

    $this->actingAs($committee_member)
        ->get(route('users.edit', $user))
        ->assertOK();
});
test('member can be casual with no ranking and no licence', function (): void {
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
            'is_committee_member' => false,
            'ranking' => 'NA',
        ])
        ->assertValid()
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();
});
test('member can be casual with valid licence and ranking', function (): void {
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
            'is_committee_member' => false,
            'licence' => '124599',
            'ranking' => 'B0',
        ])
        ->assertValid()
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();
});
test('member cannot access edit member page', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('users.edit', 1))
        ->assertStatus(403);
});
test('member cant be competitor without valid licence and ranking', function (): void {
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
            'is_committee_member' => false,
            'is_competitor' => 'on',
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
});
test('member update doesnt change entries in the database', function (): void {
    $admin = $this->createFakeAdmin();
    $user = $this->createFakeUser();

    $totalUsers = User::count();

    $this->actingAs($admin)
        ->from(route('users.edit', $user))
        ->put(route('users.update', $user), [
            'is_active' => false,
            'first_name' => 'Jean',
            'last_name' => 'Lechat',
            'sex' => 'MEN',
            'email' => 'jean.lechat@gmail.com',
            'password' => 'Jean1234!',
            'password_confirmation' => 'Jean1234!',
            'is_admin' => false,
            'is_committee_member' => false,
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
});
test('member update with invalid data is returning errors', function (): void {
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
            'is_committee_member' => null,
            'licence' => '114399',
            'is_competitor' => true,
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
});
test('members cant access edit member page', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('users.edit', $user))
        ->assertStatus(403);

    $this->actingAs($user)
        ->get(route('users.edit', $user))
        ->assertStatus(403);
});
test('unlogged user cannot access members edit', function (): void {
    $this->get(route('users.edit', 1))
        ->assertRedirect('/login');
});
