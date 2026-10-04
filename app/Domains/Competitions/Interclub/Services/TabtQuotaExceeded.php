<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Services;

use RuntimeException;

/**
 * TabT refused the call because this address spent its quota.
 *
 * Unlike every other fault it says nothing about the request: the same call
 * succeeds a few minutes later, once the allowance has drained. Callers that
 * can afford to wait catch this one and try again.
 */
class TabtQuotaExceeded extends RuntimeException
{
    /**
     * The `faultcode` TabT writes on a quota refusal.
     */
    public const string FAULT_CODE = '34';
}
