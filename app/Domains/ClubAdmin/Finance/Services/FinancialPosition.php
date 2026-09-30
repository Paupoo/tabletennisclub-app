<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Services;

use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\ValueObjects\FiscalYear;
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
    /** A bank balance older than this, on the day read, is flagged as stale. */
    public const int STALE_AFTER_DAYS = 31;

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
     * An account whose first line is later holds its opening balance
     * ({@see BankAccount::balanceAt()}), with no day it dates from; one with
     * no balance at all is shown without one — unknown is not zero — and left
     * out of the total. Each holder carries the day its balance dates from,
     * so a stale import shows as stale.
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
                'balance' => $account->balanceAt($day),
                'as_of' => $line === null ? null : CarbonImmutable::parse($line->date),
            ];
        }

        $tills = CashRegister::displayNames();

        foreach (CashRegister::query()->orderBy('id')->get() as $register) {
            $entries = $register->entries()->whereDate('created_at', '<=', $day->toDateString());
            $cents = (int) (clone $entries)->sum('amount');
            $last = (clone $entries)->max('created_at');

            $holders[] = [
                'name' => $tills[$register->id] ?? $register->name,
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

    /**
     * The money held at each month end of a financial year, up to `$until`
     * for a year still running (its last month stops at that day): one
     * series per bank account, and the tills together.
     *
     * A bank balance is the last imported one on or before the day — a
     * savings account imported twice a year steps, which is what it is. Its
     * `as_of` says which day it dates from. Before an account's first line,
     * its opening balance, the same one the treasury tile starts from. A till's balance is the sum of its
     * entries up to the day: its opening balance is an entry too, and the
     * `balance` column of `cash_registers` is read by nothing. Retired tills
     * count for the days they held money.
     *
     * @return array{series: list<array{key: string, label: string}>, months: list<array{day: CarbonImmutable, values: list<array{balance: float|null, as_of: CarbonImmutable|null}>, total: float}>}
     */
    public function treasuryByMonth(FiscalYear $year, CarbonInterface $until): array
    {
        $accounts = BankAccount::query()->orderBy('type')->orderBy('id')->get();
        $until = CarbonImmutable::parse($until->toDateString());
        $series = [
            ...$accounts->map(fn (BankAccount $account): array => ['key' => 'account-' . $account->id, 'label' => $account->name])->all(),
            ['key' => 'cash', 'label' => __('Tills')],
        ];

        $months = [];

        for ($index = 0; $index < 12; $index++) {
            $monthStart = $year->start()->addMonths($index);

            if ($monthStart->greaterThan($until)) {
                break;
            }

            $day = $monthStart->endOfMonth()->startOfDay()->min($until);
            $values = [];

            foreach ($accounts as $account) {
                $line = $account->balanceLineAt($day);
                $values[] = ['balance' => $account->balanceAt($day), 'as_of' => $line === null ? null : CarbonImmutable::parse($line->date)];
            }

            $values[] = ['balance' => $this->tillsAt($day), 'as_of' => null];

            $months[] = [
                'day' => $day,
                'values' => $values,
                'total' => round(array_sum(array_map(static fn (array $value): float => $value['balance'] ?? 0.0, $values)), 2),
            ];
        }

        return ['series' => $series, 'months' => $months];
    }

    /**
     * The treasury tile: what is held on `$day`, how much it moved since the
     * eve of the financial year — the last month of the chart minus its
     * opening, since both read {@see BankAccount::balanceAt()} — and the bank
     * balances too old to trust —
     * none known, or the last one more than {@see self::STALE_AFTER_DAYS}
     * days before `$day`. A till is never stale: every movement is recorded
     * as it happens.
     *
     * @return array{total: float, change: float, stale: list<array{name: string, as_of: CarbonImmutable|null}>}
     */
    public function treasurySummary(FiscalYear $year, CarbonInterface $day): array
    {
        $now = $this->treasuryAt($day);
        $eve = $this->treasuryAt($year->start()->subDay());
        $limit = CarbonImmutable::parse($day->toDateString())->subDays(self::STALE_AFTER_DAYS);

        $stale = array_values(array_map(
            static fn (array $holder): array => ['name' => $holder['name'], 'as_of' => $holder['as_of']],
            array_filter($now['holders'], static fn (array $holder): bool => $holder['kind'] !== 'cash'
                && ($holder['as_of'] === null || $holder['as_of']->lessThan($limit))),
        ));

        // An account first imported during the year still has a balance on
        // the eve — its opening one. Only an account with no balance at all
        // is left out.
        $change = 0.0;

        foreach ($now['holders'] as $index => $holder) {
            // A till with no entry yet held nothing; a bank account never
            // imported is unknown.
            $before = $eve['holders'][$index]['balance'] ?? ($holder['kind'] === 'cash' ? 0.0 : null);

            if ($holder['balance'] !== null && $before !== null) {
                $change += $holder['balance'] - $before;
            }
        }

        return [
            'total' => $now['total'],
            'change' => round($change, 2),
            'stale' => $stale,
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
    /**
     * What every till held at the end of a day, retired ones included.
     */
    private function tillsAt(CarbonInterface $day): float
    {
        $cents = (int) CashRegisterEntry::query()
            ->whereDate('created_at', '<=', $day->toDateString())
            ->sum('amount');

        return round($cents / 100, 2);
    }

    private function total(int $firstCents, int $secondCents, int $count): array
    {
        return ['amount' => round(($firstCents + $secondCents) / 100, 2), 'count' => $count];
    }
}
