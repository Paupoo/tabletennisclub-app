<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Domains\Shared\Enums\AttestationRefusal;
use RuntimeException;

/**
 * The club will not certify this affiliation, and says why.
 *
 * Thrown rather than returned because by the time the action runs, the screens
 * have already checked: reaching here means a rule was bypassed, and stamping
 * anything at that point would be worse than failing.
 */
final class AttestationNotAllowed extends RuntimeException
{
    public function __construct(public readonly AttestationRefusal $refusal)
    {
        parent::__construct($refusal->message());
    }
}
