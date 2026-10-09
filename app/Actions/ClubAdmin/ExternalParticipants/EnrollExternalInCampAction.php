<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\ExternalParticipants;

use App\Data\ExternalParticipant\ExternalIdentity;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Mail\ExternalCampEnrolmentEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The club puts a non-member on a stage.
 *
 * Only the club does: there is no public form, so the person encoding is the
 * filter. Encoding is the validation — a stage that sorts its members'
 * requests does not sort these.
 *
 * Members keep the stage to themselves until `externals_open_on`; from then
 * on the places are shared, first come, first served. A full stage is full:
 * a non-member has no account to confirm an offered spot with, so they never
 * join the waiting list.
 *
 * The invoice is born with the registration, and one mail both confirms and
 * asks to pay: without an account it is the only trace the person gets.
 */
final readonly class EnrollExternalInCampAction
{
    public function __construct(private TrainingCampBilling $billing = new TrainingCampBilling) {}

    /**
     * @param  float|null  $overrideAmount  In euros; a reason is then required.
     * @param  bool  $sendConfirmation  Off when the person pays cash on the spot.
     *
     * @throws \DomainException
     */
    public function __invoke(
        TrainingPack $camp,
        ExternalIdentity $identity,
        ?float $overrideAmount = null,
        ?string $overrideReason = null,
        bool $sendConfirmation = true,
    ): ExternalRegistration {
        $this->assertTakesNonMembers($camp);
        $identity->assertComplete();

        $overrideReason = $overrideReason !== null && trim($overrideReason) !== '' ? trim($overrideReason) : null;

        if ($overrideAmount !== null && $overrideAmount < 0) {
            throw new \DomainException(__('A forced amount cannot be negative.'));
        }

        if ($overrideAmount !== null && $overrideReason === null) {
            throw new \DomainException(__('A reason is required to force the amount of a training pack.'));
        }

        [$registration, $claim] = DB::transaction(function () use ($camp, $identity, $overrideAmount, $overrideReason): array {
            /** @var ExternalRegistration $registration */
            $registration = $camp->externalRegistrations()->create([
                ...$identity->toAttributes(),
                'status' => 'enrolled',
                'override_amount' => $overrideAmount !== null ? (int) round($overrideAmount * 100) : null,
                'override_reason' => $overrideAmount !== null ? $overrideReason : null,
                'created_by' => Auth::id(),
            ]);

            return [$registration, $this->billing->sync($registration)['issued']];
        });

        if ($sendConfirmation && $claim instanceof Payment) {
            Mail::to($registration->email)->send(new ExternalCampEnrolmentEmail($claim));
        }

        return $registration;
    }

    /**
     * @throws \DomainException
     */
    private function assertTakesNonMembers(TrainingPack $camp): void
    {
        if (! $camp->is_camp) {
            throw new \DomainException(__('This training pack is not a training camp.'));
        }

        if ($camp->externals_open_on === null) {
            throw new \DomainException(__('This training camp is not open to non-members.'));
        }

        if ($camp->externals_open_on->isAfter(today())) {
            throw new \DomainException(__('Members have priority: this training camp opens to non-members on :date.', [
                'date' => $camp->externals_open_on->format('d/m/Y'),
            ]));
        }

        if (! $camp->hasAvailableSpot()) {
            throw new \DomainException(__('This training camp is full.'));
        }
    }
}
