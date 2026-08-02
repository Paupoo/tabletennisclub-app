<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\FineReason;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('creates a fine and its pending payment', function (): void {
    Notification::fake();
    $member = User::factory()->create();
    $issuer = User::factory()->isAdmin()->create();

    $fine = (new IssueFine)($member, $issuer, FineReason::MISCONDUCT, 25, 'Please be more careful next time.');

    expect($fine->amount)->toBe(25.0)
        ->and($fine->payment)->not->toBeNull()
        ->and($fine->payment->status)->toBe('pending')
        ->and($fine->payment->amount_due)->toBe(25.0);

    $this->assertDatabaseHas('fines', [
        'user_id' => $member->id,
        'issued_by' => $issuer->id,
        'reason' => 'misconduct',
    ]);
});

it('labels the payment as a fine with its reason', function (): void {
    $fine = Fine::factory()->create(['reason' => FineReason::UNJUSTIFIED_ABSENCE]);

    expect($fine->getPaymentLabel())->toBe([
        'type' => __('Fine'),
        'name' => __('Unjustified absence'),
    ]);
});

it('notifies the fined member', function (): void {
    Notification::fake();
    $member = User::factory()->create();
    $issuer = User::factory()->isAdmin()->create();

    (new IssueFine)($member, $issuer, FineReason::LATE, 15, 'A note.');

    Notification::assertSentTo($member, FineIssuedNotification::class);
});

it('also notifies the guardians of a minor', function (): void {
    Notification::fake();
    $minor = User::factory()->create(['birthdate' => now()->subYears(12)]);
    $issuer = User::factory()->isAdmin()->create();
    $guardian = Guardian::factory()->create(['email' => 'parent@example.com']);
    $minor->guardians()->attach($guardian->id);

    (new IssueFine)($minor, $issuer, FineReason::FORFEIT, 20, 'A note.');

    Notification::assertSentOnDemand(FineIssuedNotification::class);
});

it('renders the pedagogical message, amount and reference in the email', function (): void {
    Club::factory()->ownClub()->create();
    $member = User::factory()->create();
    $issuer = User::factory()->isAdmin()->create();

    $fine = (new IssueFine)($member, $issuer, FineReason::MISCONDUCT, 30, 'This is your educational note.');

    $rendered = (string) new FineIssuedNotification($fine)->toMail($member)->render();

    expect($rendered)->toContain('This is your educational note.')
        ->and($rendered)->toContain($fine->payment->reference)
        ->and($rendered)->toContain('30,00');
});

it('surfaces the fine in the members payments hub', function (): void {
    Notification::fake();
    $member = User::factory()->create();
    $issuer = User::factory()->isAdmin()->create();

    (new IssueFine)($member, $issuer, FineReason::MISCONDUCT, 42, 'A note.');

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.payments', ['user' => $member])
        ->assertSee(__('Fine'))
        ->assertSee('42,00');
});

/*
| canManageFinances() used to answer this, and it was one of three divergent
| definitions of "treasurer" in the codebase. Issuing a fine is now its own duty,
| so a statutory title neither grants nor withholds it.
*/
it('gates fining on the delegation, not on a statutory title', function (): void {
    $titledButUndelegated = User::factory()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::TREASURER,
    ]);
    $delegatedWithoutTitle = User::factory()->withRole(Role::FINES)->create();

    expect(User::factory()->isAdmin()->create()->can(Permission::FinesIssue->value))->toBeTrue()
        ->and($delegatedWithoutTitle->can(Permission::FinesIssue->value))->toBeTrue()
        ->and($titledButUndelegated->can(Permission::FinesIssue->value))->toBeFalse()
        ->and(User::factory()->create()->can(Permission::FinesIssue->value))->toBeFalse();
});
