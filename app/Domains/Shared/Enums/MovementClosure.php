<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * How a movement of money — a bank line or a cash movement — left the list
 * of things to explain, or that it has not yet. Read off the movement, never
 * stored.
 *
 * Four ways out, and they are worth the same to the auditors: the website
 * accounts for it (a member's payment, a refund), a supporting document says
 * what it was, what little was left was written off on purpose, or the money
 * only moved between the club's own accounts.
 */
enum MovementClosure: string
{
    case Internal = 'internal';
    case Justified = 'justified';
    case Reconciled = 'reconciled';
    case ToProcess = 'to_process';
    case WrittenOff = 'written_off';

    /**
     * In reading order, what is left to do last.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Reconciled, self::Justified, self::WrittenOff, self::Internal, self::ToProcess];
    }

    public function isClosed(): bool
    {
        return $this !== self::ToProcess;
    }

    public function label(): string
    {
        return match ($this) {
            self::Internal => __('Internal movement'),
            self::Justified => __('Justified by a document'),
            self::Reconciled => __('Reconciled by the website'),
            self::ToProcess => __('To process'),
            self::WrittenOff => __('Residue written off'),
        };
    }
}
