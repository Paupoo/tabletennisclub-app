<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Where an expense report stands in its own life.
 *
 * "Paid" is not among them on purpose: it is read on the refund payment the
 * acceptance opened, once the bank reconciliation has settled it. Storing it
 * here too would give two truths to keep in step. {@see ExpenseReportDisplayStatus}
 * is what screens show.
 */
enum ExpenseReportStatus: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';
}
