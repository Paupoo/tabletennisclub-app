<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Actions\CancelFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineCancelledNotification;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

function cancelFineTestFine(?User $member = null): Fine
{
    return fineIssuedTo($member ?? User::factory()->create());
}

/** A fine from before members paid the committee directly: it carries a claim of the club's. */
function cancelFineTestLegacyFine(string $status): Fine
{
    $fine = Fine::factory()->create();
    $fine->payment()->create([
        'reference' => '001/2026/00042',
        'amount_due' => $fine->amount,
        'amount_paid' => $status === 'paid' ? $fine->amount : 0,
        'status' => $status,
    ]);

    return $fine;
}

it('soft-deletes the fine', function (): void {
    Notification::fake();
    $fine = cancelFineTestFine();

    (new CancelFine)($fine);

    expect(Fine::find($fine->id))->toBeNull()
        ->and(Fine::withTrashed()->find($fine->id)->trashed())->toBeTrue();
});

it('cancels the pending claim a legacy fine still carries', function (): void {
    Notification::fake();
    $fine = cancelFineTestLegacyFine('pending');

    (new CancelFine)($fine);

    expect($fine->payment->fresh()->status)->toBe('cancelled');
});

it('notifies the member that the fine was cancelled', function (): void {
    Notification::fake();
    $member = User::factory()->create();
    $fine = cancelFineTestFine($member);

    (new CancelFine)($fine);

    Notification::assertSentTo($member, FineCancelledNotification::class);
});

it('also notifies the guardians of a minor on cancellation', function (): void {
    Notification::fake();
    $minor = User::factory()->create(['birthdate' => now()->subYears(12)]);
    $guardian = Guardian::factory()->create(['email' => 'parent@example.com']);
    $minor->guardians()->attach($guardian->id);
    $fine = cancelFineTestFine($minor);

    (new CancelFine)($fine);

    Notification::assertSentOnDemand(FineCancelledNotification::class);
});

it('refuses to cancel a legacy fine whose payment has already been paid', function (): void {
    Notification::fake();
    $fine = cancelFineTestLegacyFine('paid');

    expect(fn () => (new CancelFine)($fine->fresh()))
        ->toThrow(DomainException::class);

    expect(Fine::find($fine->id))->not->toBeNull()
        ->and($fine->payment->fresh()->status)->toBe('paid');
});

it('renders the cancellation email fully in the member locale', function (): void {
    Notification::fake();
    app()->setLocale('fr_BE');
    $member = User::factory()->create();
    $fine = cancelFineTestFine($member);

    $rendered = (string) new FineCancelledNotification($fine)->toMail($member)->render();

    // Every key must resolve — a missing translation leaks the English source.
    expect($rendered)->toContain('Une amende a été annulée')
        ->and($rendered)->toContain('Bonne nouvelle')
        ->and($rendered)->not->toContain('A fine has been cancelled')
        ->and($rendered)->not->toContain('Good news');
});
