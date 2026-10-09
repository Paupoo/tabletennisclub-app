<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\ExternalParticipants;

use App\Data\ExternalParticipant\ExternalIdentity;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;

/**
 * Corrects who a non-member is: a misspelt name, a wrong address.
 *
 * The invoice is left alone — a new address does not owe anything new.
 * Sending the confirmation again is a separate gesture
 * ({@see ResendExternalConfirmationAction}).
 */
final readonly class UpdateExternalIdentityAction
{
    /**
     * @throws \DomainException
     */
    public function __invoke(ExternalRegistration $registration, ExternalIdentity $identity): void
    {
        if ($registration->isAnonymized()) {
            throw new \DomainException(__('This registration has been anonymised.'));
        }

        $identity->assertComplete();

        $registration->update($identity->toAttributes());
    }
}
