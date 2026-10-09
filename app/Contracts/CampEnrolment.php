<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notification;

/**
 * A stage enrolment that carries its own payments: a member's line or a
 * non-member's registration.
 *
 * Both follow one billing rule — what the line still claims equals what it
 * costs — so they share one billing service rather than two copies of it.
 */
interface CampEnrolment
{
    /** What the enrolment costs, in euros. */
    public function getAmountDue(): float;

    /** Owed in full: enrolled, or gone after the stage started. */
    public function isOwed(): bool;

    /** @return MorphMany<Payment, covariant \Illuminate\Database\Eloquent\Model> */
    public function payments(): MorphMany;

    /** The account a refund goes to, when the club knows one. */
    public function refundIban(): ?string;

    /** What the treasurers read when money has to go back. */
    public function refundRequestedNotification(Payment $refund, string $reason): Notification;
}
