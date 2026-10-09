<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\ExternalParticipants\AdjustExternalRegistrationAction;
use App\Actions\ClubAdmin\ExternalParticipants\CancelExternalRegistrationAction;
use App\Actions\ClubAdmin\ExternalParticipants\EnrollExternalInCampAction;
use App\Actions\ClubAdmin\ExternalParticipants\ResendExternalConfirmationAction;
use App\Actions\ClubAdmin\ExternalParticipants\UpdateExternalIdentityAction;
use App\Actions\ClubAdmin\ExternalParticipants\WithdrawExternalRegistrationAction;
use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\InviteToPayAction;
use App\Actions\ClubAdmin\Subscriptions\DiscontinueTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingCampAction;
use App\Data\ExternalParticipant\ExternalIdentity;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\ExternalParticipants\Notifications\ExternalCampCancelledNotification;
use App\Domains\ClubAdmin\ExternalParticipants\Notifications\ExternalRefundRequestedNotification;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingAttendanceReport;
use App\Domains\Trainings\Services\TrainingAttendanceService;
use App\Mail\ExternalCampEnrolmentEmail;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Club::factory()->ownClub()->create();
});

/**
 * A stage open to non-members since yesterday.
 *
 * @param  array<string, mixed>  $attributes
 */
function externalCamp(array $attributes = []): TrainingPack
{
    return TrainingPack::factory()->camp()->create([
        'season_id' => makeActiveSeason()->id,
        'price' => 80,
        'max_participants' => 10,
        'externals_open_on' => today()->subDay()->toDateString(),
        ...$attributes,
    ]);
}

function adultIdentity(string $email = 'marc.dupont@example.com'): ExternalIdentity
{
    return new ExternalIdentity(firstName: 'Marc', lastName: 'Dupont', email: $email, phone: null);
}

function childIdentity(): ExternalIdentity
{
    return new ExternalIdentity(
        firstName: 'Léa',
        lastName: 'Dupont',
        email: 'parent.dupont@example.com',
        phone: null,
        isMinor: true,
        guardianFirstName: 'Sophie',
        guardianLastName: 'Dupont',
        guardianPhone: '0470 12 34 56',
    );
}

describe('the club enrols a non-member in a stage', function (): void {
    it('enrols them, bills the external price and sends one confirmation to pay', function (): void {
        $camp = externalCamp(['external_price' => 95]);

        $registration = (new EnrollExternalInCampAction)($camp, adultIdentity());

        expect($registration->status)->toBe('enrolled')
            ->and($registration->getAmountDue())->toBe(95.0);

        $claim = $registration->payments()->sole();
        expect($claim->status)->toBe('pending')
            ->and((float) $claim->amount_due)->toBe(95.0)
            ->and($claim->reference)->not->toBeEmpty();

        Mail::assertQueued(ExternalCampEnrolmentEmail::class, fn (ExternalCampEnrolmentEmail $mail): bool => $mail->hasTo('marc.dupont@example.com')
            && $mail->payment->is($claim));
        Mail::assertQueuedCount(1);
    });
});

describe('when the stage takes non-members', function (): void {
    it('refuses a stage that never opened to non-members', function (): void {
        $camp = externalCamp(['externals_open_on' => null]);

        expect(fn () => (new EnrollExternalInCampAction)($camp, adultIdentity()))
            ->toThrow(DomainException::class, __('This training camp is not open to non-members.'));
    });

    it('keeps the stage to members until the opening day', function (): void {
        $camp = externalCamp(['externals_open_on' => today()->addDay()->toDateString()]);

        (new EnrollExternalInCampAction)($camp, adultIdentity());
    })->throws(DomainException::class);

    it('takes non-members from the opening day itself', function (): void {
        $camp = externalCamp(['externals_open_on' => today()->toDateString()]);

        expect((new EnrollExternalInCampAction)($camp, adultIdentity())->status)->toBe('enrolled');
    });

    it('locks the stage nature once a non-member is on it', function (): void {
        $camp = externalCamp();
        (new EnrollExternalInCampAction)($camp, adultIdentity());

        expect($camp->hasEverHadEnrolments())->toBeTrue();
    });

    it('refuses a pack that is not a stage', function (): void {
        $pack = TrainingPack::factory()->create(['season_id' => makeActiveSeason()->id, 'externals_open_on' => today()->toDateString()]);

        expect(fn () => (new EnrollExternalInCampAction)($pack, adultIdentity()))
            ->toThrow(DomainException::class, __('This training pack is not a training camp.'));
    });

    it('shares the places with members and refuses a full stage, with no waiting list', function (): void {
        $camp = externalCamp(['max_participants' => 1]);
        (new EnrollExternalInCampAction)($camp, adultIdentity('first@example.com'));

        expect($camp->fresh()->hasAvailableSpot())->toBeFalse();

        expect(fn () => (new EnrollExternalInCampAction)($camp->fresh(), adultIdentity('second@example.com')))
            ->toThrow(DomainException::class, __('This training camp is full.'));
    });

    it('lets members waiting in line count against the places a non-member wants', function (): void {
        $camp = externalCamp(['max_participants' => 1]);
        $subscription = Subscription::factory()
            ->for(User::factory())
            ->create(['season_id' => $camp->season_id, 'status' => 'paid']);
        (new EnrollInTrainingCampAction)($subscription, $camp, byClub: true);

        expect(fn () => (new EnrollExternalInCampAction)($camp->fresh(), adultIdentity()))
            ->toThrow(DomainException::class, __('This training camp is full.'));
    });
});

describe('who a non-member is', function (): void {
    it('records a child with the adult who answers for them, and writes to that adult', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), childIdentity());

        expect($registration->is_minor)->toBeTrue()
            ->and($registration->guardian_first_name)->toBe('Sophie')
            ->and($registration->guardian_phone)->toBe('0470 12 34 56')
            ->and($registration->phone)->toBeNull();

        Mail::assertQueued(ExternalCampEnrolmentEmail::class, fn (ExternalCampEnrolmentEmail $mail): bool => $mail->hasTo('parent.dupont@example.com'));
    });

    it('requires the adult and their number for a child', function (): void {
        $identity = new ExternalIdentity(firstName: 'Léa', lastName: 'Dupont', email: 'parent@example.com', isMinor: true, guardianFirstName: 'Sophie', guardianLastName: 'Dupont');

        expect(fn () => (new EnrollExternalInCampAction)(externalCamp(), $identity))
            ->toThrow(DomainException::class, __('The phone number of the responsible adult is required for a minor.'));
    });

    it('requires a valid address to send the invoice to', function (): void {
        expect(fn () => (new EnrollExternalInCampAction)(externalCamp(), adultIdentity('not-an-address')))
            ->toThrow(DomainException::class, __('A valid email address is required.'));
    });

    it('keeps the confirmation for later when the club says so', function (): void {
        (new EnrollExternalInCampAction)(externalCamp(), adultIdentity(), sendConfirmation: false);

        Mail::assertNothingQueued();
    });

    it('renders the confirmation with the stage, its dates and the payment details', function (): void {
        $camp = externalCamp(['name' => 'Stage de Noël', 'pack_start_date' => '2026-12-27', 'pack_end_date' => '2026-12-30']);
        $registration = (new EnrollExternalInCampAction)($camp, childIdentity());

        $html = new ExternalCampEnrolmentEmail($registration->payments()->sole())->render();

        expect($html)->toContain('Sophie')
            ->toContain('Léa Dupont')
            ->toContain('Stage de Noël')
            ->toContain('27/12/2026')
            ->toContain($registration->payments()->sole()->reference);
    });
});

describe('reminding a non-member to pay', function (): void {
    it('writes the reminder to the contact address and counts it', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), childIdentity());
        $claim = $registration->payments()->sole();

        $recipients = (new InviteToPayAction)($claim);

        expect($recipients)->toBe(['parent.dupont@example.com'])
            ->and($claim->fresh()->invitation_counter)->toBe(1);
        Mail::assertQueued(PaymentInvitationEmail::class, fn (PaymentInvitationEmail $mail): bool => $mail->hasTo('parent.dupont@example.com'));
    });

    it('greets the adult who pays by name', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), childIdentity());

        $html = new PaymentInvitationEmail($registration->payments()->sole())->render();

        expect(strip_tags($html))->toContain('Bonjour Sophie,');
    });
});

/**
 * Money received on a non-member's invoice, as a bank reconciliation would credit it.
 */
function payExternal(ExternalRegistration $registration): void
{
    $claim = $registration->payments()->where('status', 'pending')->latest('id')->first();
    (new AllocateTransactionAction)->credit($claim, (float) $claim->amount_due, 'cash');
}

describe('a non-member leaves the stage', function (): void {
    it('cancels an encoding error and its unpaid invoice', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), adultIdentity());

        $refunded = (new CancelExternalRegistrationAction)($registration);

        expect($refunded)->toBe(0.0)
            ->and($registration->fresh()->status)->toBe('cancelled')
            ->and($registration->payments()->sole()->status)->toBe('cancelled')
            ->and($registration->registrable->fresh()->committedCount())->toBe(0);
    });

    it('sends what was already paid to refund, and tells the treasurers', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        $registration = (new EnrollExternalInCampAction)(externalCamp(), adultIdentity());
        payExternal($registration);

        $refunded = (new CancelExternalRegistrationAction)($registration->fresh());

        expect($refunded)->toBe(80.0);
        $refund = $registration->payments()->where('payment_method', 'refund')->sole();
        expect($refund->status)->toBe('to_refund')
            ->and($refund->refund_iban)->toBeNull();
        Notification::assertSentTo($treasurer, ExternalRefundRequestedNotification::class);
    });

    it('keeps a withdrawal owed in full and frees the place', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), adultIdentity());

        (new WithdrawExternalRegistrationAction)($registration);

        expect($registration->fresh()->status)->toBe('left')
            ->and((float) $registration->payments()->sole()->amount_due)->toBe(80.0)
            ->and($registration->payments()->sole()->status)->toBe('pending')
            ->and($registration->registrable->fresh()->committedCount())->toBe(0);
    });

    it('forces a price with its reason and the invoice follows', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), adultIdentity());

        (new AdjustExternalRegistrationAction)($registration, 40.0, 'Two days out of four');

        expect($registration->fresh()->getAmountDue())->toBe(40.0)
            ->and((float) $registration->payments()->sole()->amount_due)->toBe(40.0);
    });

    it('can force the price at enrolment already', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), adultIdentity(), 30.0, 'Sibling of a member');

        expect((float) $registration->payments()->sole()->amount_due)->toBe(30.0)
            ->and($registration->override_reason)->toBe('Sibling of a member');
    });
});

describe('the club calls the stage off', function (): void {
    it('cancels the non-members, refunds what they paid and tells them', function (): void {
        $camp = externalCamp();
        $paid = (new EnrollExternalInCampAction)($camp, adultIdentity('paid@example.com'));
        payExternal($paid);
        $unpaid = (new EnrollExternalInCampAction)($camp, childIdentity());

        $result = (new DiscontinueTrainingPackAction)($camp->fresh(), 'Coach unavailable');

        expect($paid->fresh()->status)->toBe('cancelled')
            ->and($unpaid->fresh()->status)->toBe('cancelled')
            ->and($unpaid->payments()->sole()->status)->toBe('cancelled')
            ->and((float) $paid->payments()->where('payment_method', 'refund')->sole()->amount_due)->toBe(80.0)
            ->and($result['refunded'])->toBe(80.0);

        Notification::assertSentOnDemand(ExternalCampCancelledNotification::class, fn (ExternalCampCancelledNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'paid@example.com'
            && $notification->refundAmount === 80.0);
        Notification::assertSentOnDemand(ExternalCampCancelledNotification::class, fn (ExternalCampCancelledNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'parent.dupont@example.com');
    });

    it('greets the adult and announces the refund in the mail', function (): void {
        $registration = ExternalRegistration::factory()->minor()->create(['guardian_first_name' => 'Sophie']);

        $mail = new ExternalCampCancelledNotification($registration, 'Coach unavailable', 80.0)->toMail(new AnonymousNotifiable);

        expect($mail->greeting)->toContain('Sophie')
            ->and(implode(' ', $mail->introLines))->toContain('Coach unavailable')->toContain('80.00');
    });
});

describe('correcting a non-member', function (): void {
    it('corrects the identity without touching the invoice', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), adultIdentity());
        $claim = $registration->payments()->sole();

        (new UpdateExternalIdentityAction)($registration, adultIdentity('marc.dupond@example.com'));

        expect($registration->fresh()->email)->toBe('marc.dupond@example.com')
            ->and($registration->payments()->sole()->is($claim))->toBeTrue();
    });

    it('resends the confirmation to the corrected address', function (): void {
        $registration = (new EnrollExternalInCampAction)(externalCamp(), adultIdentity(), sendConfirmation: false);
        (new UpdateExternalIdentityAction)($registration, adultIdentity('marc.dupond@example.com'));

        expect((new ResendExternalConfirmationAction)($registration->fresh()))->toBeTrue();

        Mail::assertQueued(ExternalCampEnrolmentEmail::class, fn (ExternalCampEnrolmentEmail $mail): bool => $mail->hasTo('marc.dupond@example.com'));
    });

    it('cannot correct a registration once erased', function (): void {
        $registration = ExternalRegistration::factory()->create(['anonymized_at' => now()]);

        expect(fn () => (new UpdateExternalIdentityAction)($registration, adultIdentity()))
            ->toThrow(DomainException::class, __('This registration has been anonymised.'));
    });
});

describe('attendance of non-members', function (): void {
    it('lets the coach mark a non-member and closes the roll call with the untouched ones absent', function (): void {
        $camp = externalCamp();
        $came = (new EnrollExternalInCampAction)($camp, adultIdentity('came@example.com'));
        $silent = (new EnrollExternalInCampAction)($camp, adultIdentity('silent@example.com'));
        $session = Training::factory()->past()->for($camp, 'trainingPack')->create();
        $service = app(TrainingAttendanceService::class);

        $service->recordExternal($session, $came, 'present');
        $service->validate($session, User::factory()->create());

        expect($service->externalStatuses($session))->toBe([$came->id => 'present', $silent->id => 'absent']);
    });

    it('shows non-members in the attendance grid and counts them in the session rate', function (): void {
        $camp = externalCamp();
        $registration = (new EnrollExternalInCampAction)($camp, adultIdentity());
        $session = Training::factory()->past()->for($camp, 'trainingPack')->create();
        $service = app(TrainingAttendanceService::class);
        $service->recordExternal($session, $registration, 'present');
        $service->validate($session, User::factory()->create());

        $matrix = app(TrainingAttendanceReport::class)->matrix($camp);

        expect($matrix['externals'])->toHaveCount(1)
            ->and($matrix['externals'][0]['name'])->toBe('Dupont Marc')
            ->and($matrix['externals'][0]['cells'][$session->id])->toBe('present')
            ->and($matrix['externals'][0]['rate'])->toBe(100)
            ->and($matrix['sessions'][0]['rate'])->toBe(100);
    });

    it('refuses to call it an encoding error once the coach saw them come', function (): void {
        $camp = externalCamp();
        $registration = (new EnrollExternalInCampAction)($camp, adultIdentity());
        $session = Training::factory()->past()->for($camp, 'trainingPack')->create();
        app(TrainingAttendanceService::class)->recordExternal($session, $registration, 'present');

        expect(fn () => (new CancelExternalRegistrationAction)($registration))->toThrow(DomainException::class);
        expect($registration->fresh()->status)->toBe('enrolled');
    });

    it('erases the absences of an encoding error', function (): void {
        $camp = externalCamp();
        $registration = (new EnrollExternalInCampAction)($camp, adultIdentity());
        $session = Training::factory()->past()->for($camp, 'trainingPack')->create();
        app(TrainingAttendanceService::class)->recordExternal($session, $registration, 'absent');

        (new CancelExternalRegistrationAction)($registration);

        expect(app(TrainingAttendanceService::class)->externalStatuses($session))->toBe([]);
    });
});
