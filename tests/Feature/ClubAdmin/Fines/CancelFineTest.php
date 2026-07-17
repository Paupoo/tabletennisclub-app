<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Actions\CancelFine;
use App\Domains\ClubAdmin\Fines\Actions\IssueFine;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineCancelledNotification;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

function issuePendingFine(?User $member = null): Fine
{
    $member ??= User::factory()->create();
    $issuer = User::factory()->create(['is_admin' => true]);

    return (new IssueFine)($member, $issuer, FineReason::MISCONDUCT, 25, 'Please be careful next time.');
}

it('cancels the pending payment and soft-deletes the fine', function (): void {
    Notification::fake();
    $fine = issuePendingFine();

    (new CancelFine)($fine);

    expect(Fine::find($fine->id))->toBeNull()
        ->and(Fine::withTrashed()->find($fine->id)->trashed())->toBeTrue()
        ->and($fine->payment->fresh()->status)->toBe('cancelled');
});

it('notifies the member that the fine was cancelled', function (): void {
    Notification::fake();
    $member = User::factory()->create();
    $fine = issuePendingFine($member);

    (new CancelFine)($fine);

    Notification::assertSentTo($member, FineCancelledNotification::class);
});

it('also notifies the guardians of a minor on cancellation', function (): void {
    Notification::fake();
    $minor = User::factory()->create(['birthdate' => now()->subYears(12)]);
    $guardian = Guardian::factory()->create(['email' => 'parent@example.com']);
    $minor->guardians()->attach($guardian->id);
    $fine = issuePendingFine($minor);

    (new CancelFine)($fine);

    Notification::assertSentOnDemand(FineCancelledNotification::class);
});

it('refuses to cancel a fine whose payment has already been paid', function (): void {
    Notification::fake();
    $fine = issuePendingFine();
    $fine->payment->update(['status' => 'paid']);

    expect(fn () => (new CancelFine)($fine->fresh()))
        ->toThrow(DomainException::class);

    expect(Fine::find($fine->id))->not->toBeNull()
        ->and($fine->payment->fresh()->status)->toBe('paid');
});

it('renders the cancellation email fully in the member locale', function (): void {
    Notification::fake();
    app()->setLocale('fr_BE');
    $member = User::factory()->create();
    $fine = issuePendingFine($member);

    $rendered = (string) (new FineCancelledNotification($fine))->toMail($member)->render();

    // Every key must resolve — a missing translation leaks the English source.
    expect($rendered)->toContain('Une amende a été annulée')
        ->and($rendered)->toContain('Bonne nouvelle')
        ->and($rendered)->not->toContain('A fine has been cancelled')
        ->and($rendered)->not->toContain('Good news');
});
