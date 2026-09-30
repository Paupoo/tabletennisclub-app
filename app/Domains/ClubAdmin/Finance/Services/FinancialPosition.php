<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Services;

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the club stands: what it is owed, what it owes, how many of its
 * members owe it something, and the money it holds.
 *
 * States, next to the flows of {@see FinancialReport}: they describe a day,
 * not a year, and are never compared with the year before. Open debts and
 * receivables are today's — an invoice paid since is no longer a debt, and
 * the website keeps no history of what used to be owed.
 */
final class FinancialPosition
{
    /**
     * The payables that name a member.
     *
     * @var list<class-string>
     */
    private const array MEMBER_PAYABLES = [Subscription::class, TournamentRegistration::class, MeetingUser::class, Registration::class];

    /**
     * The active members — affiliation confirmed or paid this season — who
     * have at least one payment still pending, next to how many active
     * members there are.
     *
     * @return array{count: int, active: int}
     */
    public function membersWithOpenDebt(): array
    {
        $active = User::query()->active()->pluck('users.id');

        $owing = $this->pendingClaims()
            ->whereHasMorph('payable', self::MEMBER_PAYABLES, fn (Builder $payable): Builder => $payable->whereIn('user_id', $active))
            ->with('payable')
            ->get()
            ->map(fn (Payment $payment): mixed => $payment->payable?->getAttribute('user_id'))
            ->filter()
            ->unique();

        return ['count' => $owing->count(), 'active' => $active->count()];
    }

    /**
     * Money the club must still pay: refunds and expense reports accepted
     * but not wired yet, and expense documents no movement paid.
     *
     * @return array{amount: float, count: int}
     */
    public function openDebts(): array
    {
        $refunds = Payment::query()->where('status', 'to_refund')->get(['id', 'amount_due', 'amount_paid']);
        $invoices = SupportingDocument::query()->expenses()->toSettle()->get(['id', 'amount']);

        return $this->total(
            $refunds->sum(fn (Payment $payment): int => max(0, $this->cents($payment->amount_due) - $this->cents($payment->amount_paid))),
            $invoices->sum(fn (SupportingDocument $document): int => $this->cents($document->amount)),
            $refunds->count() + $invoices->count(),
        );
    }

    /**
     * Money the club is still owed: what members have left to pay, and
     * income documents — a subsidy promised, a sponsor's invoice — no
     * movement brought in yet.
     *
     * @return array{amount: float, count: int}
     */
    public function openReceivables(): array
    {
        $claims = $this->pendingClaims()->get(['id', 'amount_due', 'amount_paid']);
        $incomes = SupportingDocument::query()->incomes()->toSettle()->get(['id', 'amount']);

        return $this->total(
            $claims->sum(fn (Payment $payment): int => max(0, $this->cents($payment->amount_due) - $this->cents($payment->amount_paid))),
            $incomes->sum(fn (SupportingDocument $document): int => $this->cents($document->amount)),
            $claims->count() + $incomes->count(),
        );
    }

    /**
     * The money held at the end of a day: every bank account, as the bank
     * printed its balance on the last line up to that day, and every till.
     *
     * An account with no balance that old is shown without one — unknown is
     * not zero — and left out of the total. Each holder carries the day its
     * balance dates from, so a stale import shows as stale.
     *
     * @return array{total: float, holders: list<array{name: string, kind: string, balance: float|null, as_of: CarbonImmutable|null}>}
     */
    public function treasuryAt(CarbonInterface $day): array
    {
        $holders = [];

        foreach (BankAccount::query()->orderBy('type')->orderBy('id')->get() as $account) {
            $line = $account->balanceLineAt($day);

            $holders[] = [
                'name' => $account->name,
                'kind' => $account->type->value,
                'balance' => $line?->balance_after,
                'as_of' => $line === null ? null : CarbonImmutable::parse($line->date),
            ];
        }

        foreach (CashRegister::query()->orderBy('id')->get() as $register) {
            $entries = $register->entries()->whereDate('created_at', '<=', $day->toDateString());
            $cents = (int) (clone $entries)->sum('amount');
            $last = (clone $entries)->max('created_at');

            $holders[] = [
                'name' => $register->name,
                'kind' => 'cash',
                'balance' => $last === null ? null : round($cents / 100, 2),
                'as_of' => $last === null ? null : CarbonImmutable::parse($last)->startOfDay(),
            ];
        }

        return [
            'total' => round(array_sum(array_map(static fn (array $holder): float => $holder['balance'] ?? 0.0, $holders)), 2),
            'holders' => $holders,
        ];
    }

    private function cents(float|int|null $euros): int
    {
        return (int) round((float) $euros * 100);
    }

    /**
     * Payments a member still has to make. A refund line is money going the
     * other way, whatever status an older import left on it.
     *
     * @return Builder<Payment>
     */
    private function pendingClaims(): Builder
    {
        return Payment::query()
            ->where('status', 'pending')
            ->where(fn (Builder $method): Builder => $method->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'));
    }

    /**
     * @return array{amount: float, count: int}
     */
    private function total(int $firstCents, int $secondCents, int $count): array
    {
        return ['amount' => round(($firstCents + $secondCents) / 100, 2), 'count' => $count];
    }
}
