<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\ExternalParticipants;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\Trainings\Services\TrainingWaitlistService;

/**
 * A non-member withdraws from a stage: the place frees up, the price stays owed.
 *
 * As for a member, a withdrawal is owed in full; a gesture is a forced price
 * with its reason ({@see AdjustExternalRegistrationAction}).
 */
final readonly class WithdrawExternalRegistrationAction
{
    /**
     * @throws \DomainException
     */
    public function __invoke(ExternalRegistration $registration): void
    {
        if ($registration->status !== 'enrolled') {
            throw new \DomainException(__('Only an enrolled participant can withdraw.'));
        }

        $registration->update(['status' => 'left']);

        app(TrainingWaitlistService::class)->releaseSpot($registration->registrable);
    }
}
