<?php

namespace Tests\Feature\User;

use App\Enums\Ranking;
use App\Enums\Sex;
use App\Http\Controllers\UserController;
use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Services\ForceList;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;
use Tests\Trait\CreateUser;

class CreateUserTest extends TestCase
{
    use CreateUser;
    private string $password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->password = Hash::make('password');
    }

    public function test_forceListService_is_injected(): void
    {
        // Create a mock to use ForceList service
        $forceListMock = $this->createMock(ForceList::class);

        // Inject the mock into UserController's constructor
        $controller = new UserController($forceListMock);

        // Check that the controller is correctly instanciated
        $this->assertInstanceOf(UserController::class, $controller);

        // Use ReflexionClass to access protected or private forceList property
        $reflection = new ReflectionClass($controller);
        $property = $reflection->getProperty('forceList');
        $property->setAccessible(true);

        $this->assertSame($forceListMock, $property->getValue($controller));
    }

    public function test_create_method_returning_expected_view_and_data(): void
    {
        $admin = $this->createFakeAdmin();

        $response = $this->actingAs($admin)
            ->get(route('users.create'));

        $response
            ->assertOk()
            ->assertViewIs('admin.users.create')
            ->assertViewHasAll([
                'teams' => Team::with('league')->get(),
                'rankings' => collect(Ranking::cases())->pluck('name')->toArray(),
                'sexes' => collect(Sex::cases())->pluck('name')->toArray(),
            ]);
    }


    public function test_new_member_creation_positive_case(): void
    {
        $totalMembers = User::count();
        $admin = $this->createFakeAdmin();

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post('/admin/users', [
                'birthdate' => Date::create(1988, 8, 17),
                'city_code' => '1340',
                'city_name' => 'Ottignies',
                'email' => 'charles.dupont@gmail.com',
                'email_verified_at' => now(),
                'first_name' => 'Charles',
                'is_active' => true,
                'is_competitor' => true,
                'last_name' => 'Dupont',
                'licence' => 123456,
                'password' => $this->password,
                'password_confirmation' => $this->password,
                'phone_number' => '0479123456',
                'ranking' => 'B0',
                'remember_token' => Str::random(10),
                'sex' => 'MEN',
            ])
            ->assertValid()
            ->assertRedirect(route('users.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('users', $totalMembers + 2);
    }

    public function test_new_nember_created_is_automatically_linked_to_the_club(): void
    {
        $user = User::factory()->create();
        $club = Club::firstWhere('licence', config('app.club_licence'));
        $this->assertEquals($club->id, $user->club_id);
    }

    public function test_new_member_creation_with_invalid_paramaters_returns_errors_in_the_session(): void
    {
        $admin = $this->createFakeAdmin();

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post('/admin/users', [
                'last_name' => null,
                'first_name' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Provident autem, quod eos rerum tempore iure sit inventore. Laboriosam corrupti libero et reiciendis consequuntur cumque alias ex repellat nulla, temporibus dolore. This is mini Lorem exceeding 255 characters',
                'sex' => 'wrong',
                'email' => 'aurelien.paulus@com',
                'password' => '1234',
                'password_confirmation' => '4321',
                'licence' => 1234567,
                'ranking' => 'B5',
                'birthdate' => Date::create(1988, 8, 17)->format('H:i'),
                'phone_number' => 'abc0479123456',
            ])
            ->assertInvalid([
                'last_name',
                'first_name',
                'sex',
                'email',
                'password',
                'licence',
                'ranking',
                'birthdate',
                'phone_number',
            ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'last_name',
                'first_name',
                'sex',
                'email',
                'password',
                'licence',
                'ranking',
                'birthdate',
                'phone_number',
            ]);
    }

    public function test_email_is_not_already_taken(): void
    {
        $admin = $this->createFakeAdmin();

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post('/admin/users', [
                'last_name' => 'Jules',
                'first_name' => 'Destrée',
                'sex' => 'MEN',
                'email' => 'aurelien.paulus@gmail.com',
                'password' => 'z8XDbhN5sFHjWv',
                'password_confirmation' => 'z8XDbhN5sFHjWv!',
                'licence' => 123456,
                'ranking' => 'B2',
                'birthdate' => Date::create(1988, 8, 17),
                'phone_number' => '0479123456',
            ])
            ->assertInvalid('email')
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'email' => 'The email has already been taken.',
            ]);

    }

    public function test_licence_is_not_already_taken(): void
    {
        $admin = $this->createFakeAdmin();
        $licenceAlreadyUsed = User::firstWhere('licence', '!=', null)->licence;

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post('/admin/users', [
                'last_name' => 'Jules',
                'first_name' => 'Destrée',
                'sex' => 'MEN',
                'email' => 'jules.destree@gmail.com',
                'password' => 'z8XDbhN5sFHjWv!',
                'password_confirmation' => 'z8XDbhN5sFHjWv!',
                'licence' => $licenceAlreadyUsed,
                'ranking' => 'B2',
                'birthdate' => Date::create(1988, 8, 17),
                'phone_number' => '0479123456',
            ])
            ->assertInvalid('licence')
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'licence' => 'The licence has already been taken.',
            ]);
    }
    public function test_ranking_is_invalid(): void
    {
        $admin = $this->createFakeAdmin();

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post('/admin/users', [
                'last_name' => 'Jules',
                'first_name' => 'Destrée',
                'sex' => 'MEN',
                'email' => 'jules.destree@gmail.com',
                'password' => 'z8XDbhN5sFHjWv!',
                'password_confirmation' => 'z8XDbhN5sFHjWv!',
                'licence' => '123456',
                'ranking' => 'E3',
                'birthdate' => Date::create(1988, 8, 17),
                'phone_number' => '0479123456',
            ])
            ->assertInvalid('ranking')
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'ranking',
            ]);
    }

    public function test_new_member_is_competitor_with_valid_ranking_and_licence(): void
    {
        $admin = $this->createFakeAdmin();

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post('/admin/users', [
                'is_competitor' => 'on',
                'last_name' => 'Jules',
                'first_name' => 'Destrée',
                'sex' => 'MEN',
                'email' => 'jules.destree@gmail.com',
                'password' => 'z8XDbhN5sFHjWv!',
                'password_confirmation' => 'z8XDbhN5sFHjWv!',
                'licence' => '123456',
                'ranking' => 'E0',
                'birthdate' => Date::create(1988, 8, 17),
                'phone_number' => '0479123456',
            ])
            ->assertValid()
            ->assertRedirect(route('users.create'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_new_member_is_competitor_without_ranking_and_licence(): void
    {
        $admin = $this->createFakeAdmin();

        $this->actingAs($admin)
            ->from(route('users.create'))
            ->post('/admin/users', [
                'is_competitor' => 'on',
                'last_name' => 'Jules',
                'first_name' => 'Destrée',
                'sex' => 'MEN',
                'email' => 'jules.destree@gmail.com',
                'password' => 'z8XDbhN5sFHjWv!',
                'password_confirmation' => 'z8XDbhN5sFHjWv!',
                'birthdate' => Date::create(1988, 8, 17),
                'phone_number' => '0479123456',
            ])
            ->assertInvalid([
                'licence',
                'ranking'
                ])
            ->assertRedirect(route('users.create'))
            ->assertSessionHasErrors([
                'licence',
                'ranking',
            ]);
    }

    public function test_member_cannot_create_new_member(): void
    {
        $user = $this->createFakeUser();

        $this->actingAs($user)
            ->post('/admin/users/create')
            ->assertStatus(405);
    }
}
