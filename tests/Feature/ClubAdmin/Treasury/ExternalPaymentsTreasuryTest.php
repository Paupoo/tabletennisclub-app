<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\ExternalParticipants\EnrollExternalInCampAction;
use App\Actions\ClubAdmin\Payments\OpenRefundAction;
use App\Data\ExternalParticipant\ExternalIdentity;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Payment\Notifications\WeeklyRefundReminderNotification;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\TrainingPack;
use App\Jobs\SendPaymentReminderJob;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

/*
 * A non-member's stage invoice lives in the treasury like any other: listed
 * under their name, found by it, filed with the stages, and chased at the
 * address encoded with the registration.
 */

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    Mail::fake();
    $this->treasurer = $this->createFakeAdmin();

    $camp = TrainingPack::factory()->camp()->create([
        'season_id' => makeActiveSeason()->id,
        'name' => 'Stage de Noël',
        'price' => 80,
        'externals_open_on' => today()->toDateString(),
    ]);

    $this->registration = (new EnrollExternalInCampAction)($camp, new ExternalIdentity(
        firstName: 'Léa',
        lastName: 'Vandenbossche',
        email: 'parent@example.com',
        isMinor: true,
        guardianFirstName: 'Sophie',
        guardianLastName: 'Vandenbossche',
        guardianPhone: '0470 12 34 56',
    ), sendConfirmation: false);

    $this->claim = $this->registration->payments()->sole();
});

it('lists the invoice under the participant name, as a stage', function (): void {
    $rows = Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->viewData('payments');

    $row = collect($rows->items())->firstWhere('reference', $this->claim->reference);

    expect($row->member)->toBe('Léa Vandenbossche')
        ->and($row->event_type)->toBe(__('Training camp'))
        ->and($row->event_name)->toBe('Stage de Noël');
});

it('finds the invoice by the participant name', function (): void {
    $rows = Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('search', 'Vandenbossche')
        ->viewData('payments');

    expect(collect($rows->items())->pluck('reference')->all())->toBe([$this->claim->reference]);
});

it('files it with the stages when filtering by kind', function (): void {
    $rows = Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('eventType', SubscriptionTrainingPack::class)
        ->viewData('payments');

    expect(collect($rows->items())->pluck('reference')->all())->toContain($this->claim->reference);
});

it('chases a non-member at their address', function (): void {
    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('sendReminder', $this->claim->id);

    Mail::assertQueued(PaymentInvitationEmail::class, fn (PaymentInvitationEmail $mail): bool => $mail->hasTo('parent@example.com'));
    expect($this->claim->fresh()->invitation_counter)->toBe(1);
});

it('has nobody to chase once the participant was erased', function (): void {
    $this->registration->update(['email' => null, 'first_name' => null, 'last_name' => null, 'anonymized_at' => now()]);

    Livewire::actingAs($this->treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->call('sendReminder', $this->claim->id);

    Mail::assertNothingOutgoing();
    expect(ExternalRegistration::sole()->displayName())->toBe(__('External participant no. :id', ['id' => $this->registration->id]));
});

it('lists a refund owed to a non-member in the weekly reminder', function (): void {
    Notification::fake();
    $treasurer = User::factory()->withRole(Role::TREASURY)->create();
    (new OpenRefundAction)->forPayable($this->registration, 80.0);

    $this->artisan('payment:send-refund-reminder')->assertSuccessful();

    Notification::assertSentTo($treasurer, WeeklyRefundReminderNotification::class, fn (WeeklyRefundReminderNotification $notification): bool => str_contains(implode(' ', $notification->toMail($treasurer)->introLines), 'Léa Vandenbossche'));
});

it('chases a non-member from the bulk reminder too', function (): void {
    new SendPaymentReminderJob($this->claim->id)->handle();

    Mail::assertQueued(PaymentInvitationEmail::class, fn (PaymentInvitationEmail $mail): bool => $mail->hasTo('parent@example.com'));
});
