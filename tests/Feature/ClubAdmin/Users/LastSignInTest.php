<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Support\AccountProxy;
use Spatie\Activitylog\Models\Activity;

pest()->group('club-admin', 'users');

it('records the moment a member signs in', function (): void {
    $member = User::factory()->create(['last_login_at' => null]);

    $this->post('/login', ['email' => $member->email, 'password' => 'password']);

    expect($member->fresh()->last_login_at)->not->toBeNull()
        ->and($member->fresh()->last_login_at->diffInSeconds(now(), true))->toBeLessThan(60);
});

it('leaves the file untouched otherwise: no update date, no audit line', function (): void {
    $member = User::factory()->create();
    $member->forceFill(['updated_at' => now()->subMonth()])->saveQuietly();
    $updatedAt = $member->fresh()->updated_at;
    $auditLines = Activity::query()->count();

    $this->post('/login', ['email' => $member->email, 'password' => 'password']);

    expect($member->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(Activity::query()->count())->toBe($auditLines);
});

it('does not count a failed attempt as a sign-in', function (): void {
    $member = User::factory()->create(['last_login_at' => null]);

    $this->post('/login', ['email' => $member->email, 'password' => 'wrong-password']);

    expect($member->fresh()->last_login_at)->toBeNull();
});

it('does not read a guardian taking or giving back a ward seat as a sign-in', function (): void {
    $guardianMember = User::factory()->create(['last_login_at' => null]);
    $ward = User::factory()->create(['email' => null, 'last_login_at' => null]);
    $guardian = Guardian::factory()->create(['user_id' => $guardianMember->id]);
    $ward->guardians()->attach($guardian->id);

    $this->actingAs($guardianMember);

    AccountProxy::start($ward);
    AccountProxy::stop();

    expect($ward->fresh()->last_login_at)->toBeNull()
        ->and($guardianMember->fresh()->last_login_at)->toBeNull();
});
