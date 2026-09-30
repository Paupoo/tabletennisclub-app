<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Domains\Shared\Support\IbanNormalizer;
use DomainException;

/**
 * A statement of an account the club has not registered.
 *
 * Nothing was imported. The screen catches it to ask the treasurer whether
 * the account is a new club account, then imports again.
 */
final class UnknownBankAccount extends DomainException
{
    /**
     * @param  non-empty-list<string>  $ibans  Normalised, in the order the statement names them.
     */
    public function __construct(public readonly array $ibans)
    {
        parent::__construct(__('This statement is for account :account, which is not registered as a club account.', [
            'account' => IbanNormalizer::format($ibans[0]),
        ]));
    }
}
