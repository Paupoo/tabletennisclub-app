<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\FineReason;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('records the fine with its event and deadline', function (): void {
    Notification::fake();
    $member = User::factory()->create();

    $fine = fineIssuedTo($member, FineReason::REFEREEING, 15);

    expect($fine->amount)->toBe(15.0)
        ->and($fine->reason)->toBe(FineReason::REFEREEING)
        ->and($fine->provincial_code)->toBe(67)
        ->and($fine->event_label)->toBe('LA HULPE RIXENSART')
        ->and($fine->event_date->toDateString())->toBe(today()->subWeek()->toDateString())
        ->and($fine->payment_deadline->toDateString())->toBe(today()->addWeeks(2)->toDateString());
});

/*
 * The member pays the provincial committee directly. A claim of the club's
 * would stay open forever: no transfer to the club will ever settle it.
 */
it('creates no payment of the club for the fine', function (): void {
    Notification::fake();

    $fine = fineIssuedTo(User::factory()->create());

    expect($fine->payment)->toBeNull()
        ->and(Payment::count())->toBe(0);
});

it('refuses to issue a fine while the committee account is unknown', function (): void {
    Notification::fake();
    $member = User::factory()->create();

    expect(fn () => (new IssueFine)(
        $member,
        User::factory()->isAdmin()->create(),
        FineReason::REFEREEING,
        15,
        'A note.',
        today()->subWeek(),
        'CHAMP. SEN.',
        today()->addWeeks(2),
    ))->toThrow(DomainException::class);

    expect(Fine::count())->toBe(0);
    Notification::assertNothingSent();
});

it('notifies the fined member', function (): void {
    Notification::fake();
    $member = User::factory()->create();

    fineIssuedTo($member);

    Notification::assertSentTo($member, FineIssuedNotification::class);
});

it('also notifies the guardians of a minor', function (): void {
    Notification::fake();
    $minor = User::factory()->create(['birthdate' => now()->subYears(12)]);
    $guardian = Guardian::factory()->create(['email' => 'parent@example.com']);
    $minor->guardians()->attach($guardian->id);

    fineIssuedTo($minor);

    Notification::assertSentOnDemand(FineIssuedNotification::class);
});

it('writes to the address a guardian holding an account keeps in their profile', function (): void {
    Notification::fake();
    $parent = User::factory()->create(['email' => 'old@example.com']);
    $minor = User::factory()->create(['birthdate' => now()->subYears(12)]);
    $minor->guardians()->attach(Guardian::factory()->create(['user_id' => $parent->id, 'email' => 'old@example.com']));
    $parent->update(['email' => 'new@example.com']);

    fineIssuedTo($minor);

    Notification::assertSentOnDemand(
        FineIssuedNotification::class,
        fn ($notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'new@example.com',
    );
});

it('tells the member to pay the committee, not the club, before the deadline', function (): void {
    Notification::fake();
    $member = User::factory()->create(['first_name' => 'Jeremy', 'last_name' => 'Denil']);

    $fine = fineIssuedTo($member, FineReason::UNANNOUNCED_ABSENCE, 35);

    $rendered = (string) new FineIssuedNotification($fine)->toMail($member)->render();

    expect($rendered)->toContain('Please be careful next time, we are here to help.')
        ->and($rendered)->toContain('CPBBW')
        ->and($rendered)->toContain('BE50 2100 3624 5518')
        ->and($rendered)->toContain('35,00')
        ->and($rendered)->toContain(today()->addWeeks(2)->format('d/m/Y'))
        ->and($rendered)->toContain(e($fine->transferCommunication()))
        ->and($rendered)->toContain('tresorier@cpbbw.test');
});

it('builds the transfer communication from who, when and why', function (): void {
    Notification::fake();
    $member = User::factory()->create(['first_name' => 'Jeremy', 'last_name' => 'Denil']);

    $fine = fineIssuedTo($member, FineReason::UNANNOUNCED_ABSENCE);

    expect($fine->transferCommunication())
        ->toBe('DENIL Jeremy – ' . today()->subWeek()->format('d/m/Y') . ' – ' . FineReason::UNANNOUNCED_ABSENCE->label());
});

it('encodes the committee account and the communication in the QR payload', function (): void {
    $payload = (new GeneratePaymentQR)->transferText('CPBBW', 'BE50 2100 3624 5518', 35, 'DENIL Jeremy – 22/03/2026 – Absence');

    expect(explode("\n", $payload))->toBe([
        'BCD', '002', '1', 'SCT', '', 'CPBBW', 'BE50210036245518', 'EUR35.00', '', '', 'DENIL Jeremy – 22/03/2026 – Absence',
    ]);
});

it('leaves the payment instructions out once the deadline has passed', function (): void {
    fineCreditorConfigured();
    $member = User::factory()->create();
    $fine = Fine::factory()->pastDeadline()->create(['user_id' => $member->id]);

    $mail = new FineIssuedNotification($fine)->toMail($member);

    expect((string) $mail->render())->not->toContain('BE50 2100 3624 5518')
        ->and($mail->rawAttachments)->toBeEmpty();
});

it('no longer surfaces the fine in the members payments hub', function (): void {
    Notification::fake();
    $member = User::factory()->create();

    fineIssuedTo($member, FineReason::REFEREEING, 42);

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.payments', ['user' => $member])
        ->assertDontSee('42,00');
});

it('suggests the provincial code of each reason', function (FineReason $reason, ?int $code): void {
    expect($reason->provincialCode())->toBe($code);
})->with([
    [FineReason::INTERCLUB_MATCH_NOT_PLAYED, 16],
    [FineReason::UNANNOUNCED_ABSENCE, 65],
    [FineReason::ANNOUNCED_ABSENCE, 66],
    [FineReason::REFEREEING, 67],
    [FineReason::YELLOW_CARD, null],
]);

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
