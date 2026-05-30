<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use Illuminate\Support\Facades\Hash;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->password = Hash::make('password');
    $this->existingUser = User::factory()->create([
        'email' => 'aurelien.paulus@gmail.com',
        'licence' => '999888',
    ]);
    Club::factory()->create(['licence' => config('app.club_licence')]);
});
test('create method returning expected view and data', function (): void {
    $admin = $this->createFakeAdmin();

    $response = $this->actingAs($admin)
        ->get(route('admin.users.create'));

    $response->assertOk();
});
test('new nember created is automatically linked to the club', function (): void {
    $user = User::factory()->create();
    $club = Club::firstWhere('licence', config('app.club_licence'));
    expect($user->club_id)->toEqual($club->id);
});
