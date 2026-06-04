<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('users table has soft delete column', function (): void {
    expect(Schema::hasColumn('users', 'deleted_at'))->toBeTrue();
});

test('users table has updated_by column', function (): void {
    expect(Schema::hasColumn('users', 'updated_by'))->toBeTrue();
});

test('users table has last_invited_at column', function (): void {
    expect(Schema::hasColumn('users', 'last_invited_at'))->toBeTrue();
});

test('user can be soft deleted', function (): void {
    $user = User::factory()->create();

    $user->delete();

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull()
        ->and(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();
});

test('soft deleted user excluded from default queries', function (): void {
    $activeUser = User::factory()->create();
    $deletedUser = User::factory()->create();
    $deletedUser->delete();

    $ids = User::pluck('id');

    expect($ids)->toContain($activeUser->id)
        ->and($ids)->not->toContain($deletedUser->id);
});

test('soft deleted user can be restored', function (): void {
    $user = User::factory()->create();
    $user->delete();

    $user->restore();

    expect(User::find($user->id))->not->toBeNull()
        ->and(User::find($user->id)->deleted_at)->toBeNull();
});

test('user with trashed scope includes soft deleted users', function (): void {
    $user = User::factory()->create();
    $user->delete();

    expect(User::withTrashed()->find($user->id))->not->toBeNull();
});
