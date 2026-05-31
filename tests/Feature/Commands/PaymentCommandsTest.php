<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Notifications\WeeklyRefundReminderNotification;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

describe('payment:send-refund-reminder', function (): void {
    it('sends refund reminder to treasurer and secretary when pending refunds exist', function (): void {
        Notification::fake();

        $treasurer = User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::TREASURER->value]);
        $secretary = User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::SECRETARY->value]);

        $subscription = Subscription::factory()->create();
        $subscription->payments()->create([
            'reference' => 'REF/2026/00001',
            'amount_due' => 6000,
            'amount_paid' => 0,
            'status' => 'to_refund',
        ]);

        $this->artisan('payment:send-refund-reminder')->assertSuccessful();

        Notification::assertSentTo($treasurer, WeeklyRefundReminderNotification::class);
        Notification::assertSentTo($secretary, WeeklyRefundReminderNotification::class);
    });

    it('does not send reminder when there are no pending refunds', function (): void {
        Notification::fake();

        User::factory()->isCommitteeMember()->create(['committee_role' => CommitteeRolesEnum::TREASURER->value]);

        $this->artisan('payment:send-refund-reminder')->assertSuccessful();

        Notification::assertNothingSent();
    });

    it('does not send reminder when there are no committee recipients', function (): void {
        Notification::fake();

        $subscription = Subscription::factory()->create();
        $subscription->payments()->create([
            'reference' => 'REF/2026/00002',
            'amount_due' => 6000,
            'amount_paid' => 0,
            'status' => 'to_refund',
        ]);

        $this->artisan('payment:send-refund-reminder')->assertSuccessful();

        Notification::assertNothingSent();
    });
});
