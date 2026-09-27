<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Enums\Permission;
use App\Support\AccountProxy;

/**
 * Who declares, who reads, who decides on an expense report.
 *
 * Declaring is kept to adults acting for themselves: a minor is never
 * answerable for the club's money, and a guardian holding a ward's seat is
 * authenticated as the ward — so the proxy check is what keeps them out.
 *
 * Reading follows the rest of the treasury ({@see Permission::PaymentsView}),
 * which the committee and the accounts auditors hold. Deciding is its own
 * right, and never on one's own report.
 */
class ExpenseReportPolicy
{
    /**
     * Take the paid reports' originals off the server. Only a decider or
     * whoever wires refunds: their download is the one that counts as
     * archiving.
     */
    public function archive(User $user): bool
    {
        return $user->canAny([Permission::ExpenseReportsProcess->value, Permission::PaymentsRefund->value]);
    }

    /** Undo an acceptance while the refund has not left the account. */
    public function cancelAcceptance(User $user, ExpenseReport $report): bool
    {
        $refund = $report->refund;

        return $this->isDecider($user, $report)
            && $report->status === ExpenseReportStatus::Accepted
            && ($refund === null || ($refund->status === 'to_refund' && (float) $refund->amount_paid <= 0.0));
    }

    public function create(User $user): bool
    {
        return $user->isAdult() && ! AccountProxy::isActing();
    }

    /** Accept, reduce or reject. */
    public function decide(User $user, ExpenseReport $report): bool
    {
        return $this->isDecider($user, $report) && $report->status === ExpenseReportStatus::Submitted;
    }

    public function delete(User $user, ExpenseReport $report): bool
    {
        return false;
    }

    public function export(User $user): bool
    {
        return $user->can(Permission::PaymentsView->value);
    }

    /** Start a new report from a rejected one, filled in with what it said. */
    public function resume(User $user, ExpenseReport $report): bool
    {
        return $user->id === $report->user_id
            && $report->status === ExpenseReportStatus::Rejected
            && $this->create($user);
    }

    /**
     * The member's own account, or whoever wires refunds — the same rule as
     * the member file, where the IBAN is masked to everyone else.
     */
    public function seeIban(User $user, ExpenseReport $report): bool
    {
        return $user->id === $report->user_id || $user->can(Permission::PaymentsRefund->value);
    }

    public function update(User $user, ExpenseReport $report): bool
    {
        return $user->id === $report->user_id
            && $report->status === ExpenseReportStatus::Submitted
            && ! AccountProxy::isActing();
    }

    public function view(User $user, ExpenseReport $report): bool
    {
        return $user->id === $report->user_id
            || $user->can(Permission::PaymentsView->value)
            || $user->can(Permission::ExpenseReportsProcess->value);
    }

    /** The treasury's list of every report. */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentsView->value)
            || $user->can(Permission::ExpenseReportsProcess->value);
    }

    public function withdraw(User $user, ExpenseReport $report): bool
    {
        return $this->update($user, $report);
    }

    private function isDecider(User $user, ExpenseReport $report): bool
    {
        return $user->id !== $report->user_id && $user->can(Permission::ExpenseReportsProcess->value);
    }
}
