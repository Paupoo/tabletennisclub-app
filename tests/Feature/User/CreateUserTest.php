<?php

declare(strict_types=1);
use App\Enums\Ranking;
use App\Enums\Gender;
use App\Http\Controllers\UserController;
use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Services\ForceList;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(\Tests\Trait\CreateUser::class);

beforeEach(function (): void {
    $this->password = Hash::make('password');
});
test('create method returning expected view and data', function (): void {
    $admin = $this->createFakeAdmin();

    $response = $this->actingAs($admin)
        ->get(route('users.create'));

    $response
        ->assertOk()
        ->assertViewIs('admin.users.create')
        ->assertViewHasAll([
            'teams' => Team::with('league')->get(),
            'rankings' => collect(Ranking::cases())->pluck('name')->toArray(),
            'sexes' => collect(Gender::cases())->pluck('name')->toArray(),
        ]);
});
test('email is not already taken', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post('/admin/users', [
            'last_name' => 'Jules',
            'first_name' => 'Destrée',
            'gender' => 'MEN',
            'email' => 'aurelien.paulus@gmail.com',
            'password' => 'z8XDbhN5sFHjWv!',
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
});
test('force list service is injected', function (): void {
    // Create a mock to use ForceList service
    $forceListMock = $this->createMock(ForceList::class);

    // Inject the mock into UserController's constructor
    $controller = new UserController($forceListMock);

    // Check that the controller is correctly instanciated
    expect($controller)->toBeInstanceOf(UserController::class);

    // Use ReflexionClass to access protected or private forceList property
    $reflection = new ReflectionClass($controller);
    $property = $reflection->getProperty('forceList');
    $property->setAccessible(true);

    expect($property->getValue($controller))->toBe($forceListMock);
});
test('licence is not already taken', function (): void {
    $admin = $this->createFakeAdmin();
    $licenceAlreadyUsed = User::firstWhere('licence', '!=', null)->licence;

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post('/admin/users', [
            'last_name' => 'Jules',
            'first_name' => 'Destrée',
            'gender' => 'MEN',
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
});
test('member cannot create new member', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->post('/admin/users/create')
        ->assertStatus(405);
});
test('new member creation positive case', function (): void {
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
            'gender' => 'MEN',
        ])
        ->assertValid()
        ->assertRedirect(route('users.create'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $this->assertDatabaseCount('users', $totalMembers + 2);
});
test('new member creation with invalid paramaters returns errors in the session', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post('/admin/users', [
            'last_name' => null,
            'first_name' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Provident autem, quod eos rerum tempore iure sit inventore. Laboriosam corrupti libero et reiciendis consequuntur cumque alias ex repellat nulla, temporibus dolore. This is mini Lorem exceeding 255 characters',
            'gender' => 'wrong',
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
            'gender',
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
            'gender',
            'email',
            'password',
            'licence',
            'ranking',
            'birthdate',
            'phone_number',
        ]);
});
test('new member is competitor with valid ranking and licence', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post('/admin/users', [
            'is_competitor' => 'on',
            'last_name' => 'Jules',
            'first_name' => 'Destrée',
            'gender' => 'MEN',
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
});
test('new member is competitor without ranking and licence', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post('/admin/users', [
            'is_competitor' => 'on',
            'last_name' => 'Jules',
            'first_name' => 'Destrée',
            'gender' => 'MEN',
            'email' => 'jules.destree@gmail.com',
            'password' => 'z8XDbhN5sFHjWv!',
            'password_confirmation' => 'z8XDbhN5sFHjWv!',
            'birthdate' => Date::create(1988, 8, 17),
            'phone_number' => '0479123456',
        ])
        ->assertInvalid([
            'licence',
            'ranking',
        ])
        ->assertRedirect(route('users.create'))
        ->assertSessionHasErrors([
            'licence',
            'ranking',
        ]);
});
test('new nember created is automatically linked to the club', function (): void {
    $user = User::factory()->create();
    $club = Club::firstWhere('licence', config('app.club_licence'));
    expect($user->club_id)->toEqual($club->id);
});
test('ranking is invalid', function (): void {
    $admin = $this->createFakeAdmin();

    $this->actingAs($admin)
        ->from(route('users.create'))
        ->post('/admin/users', [
            'last_name' => 'Jules',
            'first_name' => 'Destrée',
            'gender' => 'MEN',
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
});
