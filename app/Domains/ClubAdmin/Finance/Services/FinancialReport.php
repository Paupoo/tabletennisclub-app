<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Services;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\BuildAttestationData;
use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\MovementClosure;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The club's accounts for one financial year: what came in and went out,
 * poste by poste, month by month, and how much of it is explained.
 *
 * The single source of the « Rapport financier » screen and of the PDF the
 * general assembly receives — neither computes a figure of its own.
 *
 * Cash basis, without exception: money counts on the day it moved — a bank
 * line on its date, a cash movement on the day it was recorded — never on the
 * date printed on an invoice. A document nobody paid yet enters no flow; it
 * is an open debt or receivable (see {@see FinancialPosition}).
 *
 * Where a movement goes:
 *
 * - **Internal** movements (between two club accounts, or between the till
 *   and the bank) are neither income nor expense: they are listed apart.
 * - Money the **website** accounts for is filed from the payment it was
 *   allocated to: an affiliation splits between membership fees and
 *   trainings pro rata of its gross composition, a tournament, meeting or
 *   event registration is an event, a bar order is the bar, an expense
 *   report refund is an expense of the report's category. The direction is
 *   the money's — a debit allocated to an affiliation is a refund, and
 *   deducts from the income it once was.
 * - Money a **supporting document** justifies goes to the documents'
 *   categories, pro rata of their amounts.
 * - A residue **written off** on purpose is « other » income or expense.
 * - Anything else is **uncategorised**: shown as such, never dropped, so the
 *   totals always add up to what the bank and the till saw.
 *
 * Amounts are worked in cents and handed out in euros.
 */
final class FinancialReport
{
    /** The poste of money nobody has explained yet. */
    public const string UNCATEGORISED = 'uncategorised';

    /**
     * Every piece of money of the year, filed: the poste it feeds (`income:…`
     * or `expense:…`), how much it adds to that poste in cents (negative for a
     * refund), the fiscal month it moved in (0 to 11), and for membership fees
     * whether the licence was a competitive one.
     *
     * @var list<array{poste: string, cents: int, month: int, competitive: bool|null}>
     */
    private array $flows = [];

    /**
     * @var list<array{date: CarbonImmutable, source: string, description: string, amount: float}>
     */
    private array $internalMovements = [];

    /**
     * Every movement of the year, however it was closed, with its absolute
     * amount in cents.
     *
     * @var list<array{closure: MovementClosure, cents: int}>
     */
    private array $movements = [];

    private function __construct(private readonly FiscalYear $year) {}

    public static function for(FiscalYear $year): self
    {
        $report = new self($year);
        $report->readBankLines();
        $report->readCashMovements();

        return $report;
    }

    public function expenses(): float
    {
        return $this->euros($this->sumWhere(fn (array $flow): bool => str_starts_with($flow['poste'], 'expense:')));
    }

    /**
     * What each expense poste cost, in reading order, uncategorised last.
     * Postes that saw no money are left out.
     *
     * @return array<string, float> keyed by {@see ExpenseCategory} value
     */
    public function expensesByCategory(): array
    {
        return $this->byPoste('expense', array_map(
            static fn (ExpenseCategory $category): string => $category->value,
            ExpenseCategory::ordered(),
        ));
    }

    /**
     * What came in, refunds of that income deducted, uncategorised credits
     * included.
     */
    public function income(): float
    {
        return $this->euros($this->sumWhere(fn (array $flow): bool => str_starts_with($flow['poste'], 'income:')));
    }

    /**
     * What each income poste brought, net of refunds, in reading order,
     * uncategorised last. Postes that saw no money are left out.
     *
     * @return array<string, float> keyed by {@see IncomeCategory} value
     */
    public function incomeByCategory(): array
    {
        return $this->byPoste('income', array_map(
            static fn (IncomeCategory $category): string => $category->value,
            IncomeCategory::ordered(),
        ));
    }

    /**
     * Money that only moved between the club's own accounts, or between the
     * till and the bank, oldest first. Signed from the side it was seen on.
     *
     * @return list<array{date: CarbonImmutable, source: string, description: string, amount: float}>
     */
    public function internalMovements(): array
    {
        $movements = $this->internalMovements;
        usort($movements, static fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $movements;
    }

    /**
     * How the year's movements were closed, counted and weighed.
     *
     * Internal movements count: they are closed too, and an auditor asks
     * about every line of the statement, not only the ones that make the
     * result.
     *
     * @return array{count: array<string, int>, amount: array<string, float>, closed_count_share: float|null, closed_amount_share: float|null}
     */
    public function justification(): array
    {
        $count = [];
        $amount = [];

        foreach (MovementClosure::ordered() as $closure) {
            $count[$closure->value] = 0;
            $amount[$closure->value] = 0;
        }

        foreach ($this->movements as $movement) {
            $count[$movement['closure']->value]++;
            $amount[$movement['closure']->value] += $movement['cents'];
        }

        $totalCount = array_sum($count);
        $totalCents = array_sum($amount);
        $open = MovementClosure::ToProcess->value;

        return [
            'count' => $count,
            'amount' => array_map($this->euros(...), $amount),
            'closed_count_share' => $totalCount === 0 ? null : round(100 * ($totalCount - $count[$open]) / $totalCount, 1),
            'closed_amount_share' => $totalCents === 0 ? null : round(100 * ($totalCents - $amount[$open]) / $totalCents, 1),
        ];
    }

    /**
     * The membership fees, split by the kind of licence they paid for.
     *
     * @return array{recreational: float, competitive: float}
     */
    public function membershipFeesByLicence(): array
    {
        $fees = array_filter($this->flows, static fn (array $flow): bool => $flow['poste'] === 'income:' . IncomeCategory::MembershipFees->value);

        return [
            'recreational' => $this->euros(array_sum(array_map(static fn (array $flow): int => $flow['competitive'] === true ? 0 : $flow['cents'], $fees))),
            'competitive' => $this->euros(array_sum(array_map(static fn (array $flow): int => $flow['competitive'] === true ? $flow['cents'] : 0, $fees))),
        ];
    }

    /**
     * Income and expenses month by month, and the result as it built up
     * over the year.
     *
     * @return list<array{month: CarbonImmutable, income: float, expenses: float, cumulative: float}>
     */
    public function monthly(): array
    {
        $months = [];
        $cumulative = 0;

        for ($index = 0; $index < 12; $index++) {
            $income = $this->sumWhere(fn (array $flow): bool => $flow['month'] === $index && str_starts_with($flow['poste'], 'income:'));
            $expenses = $this->sumWhere(fn (array $flow): bool => $flow['month'] === $index && str_starts_with($flow['poste'], 'expense:'));
            $cumulative += $income - $expenses;

            $months[] = [
                'month' => $this->year->start()->addMonths($index),
                'income' => $this->euros($income),
                'expenses' => $this->euros($expenses),
                'cumulative' => $this->euros($cumulative),
            ];
        }

        return $months;
    }

    public function result(): float
    {
        return round($this->income() - $this->expenses(), 2);
    }

    public function year(): FiscalYear
    {
        return $this->year;
    }

    /**
     * @param  list<string>  $order
     * @return array<string, float>
     */
    private function byPoste(string $direction, array $order): array
    {
        $totals = [];

        foreach ([...$order, self::UNCATEGORISED] as $key) {
            $cents = $this->sumWhere(fn (array $flow): bool => $flow['poste'] === $direction . ':' . $key);

            if ($cents !== 0) {
                $totals[$key] = $this->euros($cents);
            }
        }

        return $totals;
    }

    private function cents(float $euros): int
    {
        return (int) round($euros * 100);
    }

    private function euros(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * File money under a poste. `$moneyCents` is signed like the money
     * (positive when it came in); the poste receives it with the sign that
     * poste reads — an expense grows when money goes out.
     */
    private function file(string $direction, string $category, int $moneyCents, int $month, ?bool $competitive = null): void
    {
        if ($moneyCents === 0) {
            return;
        }

        $this->flows[] = [
            'poste' => $direction . ':' . $category,
            'cents' => $direction === 'income' ? $moneyCents : -$moneyCents,
            'month' => $month,
            'competitive' => $competitive,
        ];
    }

    /**
     * Money the website accounts for, filed from what was paid.
     *
     * Read from the payable's class, not from `payment_method` nor from the
     * status: both carry contradictory vocabularies in older data, and the
     * money's direction is already known from the movement.
     */
    private function fileSiteMoney(?Model $payable, int $moneyCents, int $month): void
    {
        match (true) {
            $payable instanceof Payment => $this->fileSiteMoney($payable->payable, $moneyCents, $month),
            $payable instanceof Subscription => $this->fileSubscription($payable, $moneyCents, $month),
            $payable instanceof ExpenseReport => $this->fileUnder($payable->category, $moneyCents, $month),
            $payable instanceof BarOrder => $this->fileUnder(IncomeCategory::Bar, $moneyCents, $month),
            $payable instanceof TournamentRegistration,
            $payable instanceof Tournament,
            $payable instanceof MeetingUser,
            $payable instanceof Meeting,
            $payable instanceof Registration => $this->fileUnder(IncomeCategory::Event, $moneyCents, $month),
            default => $this->fileUncategorised($moneyCents, $month),
        };
    }

    /**
     * An affiliation pays a licence and trainings: its money splits between
     * membership fees and trainings pro rata of their gross prices — the
     * licence, and what the trainings cost before the family credit and the
     * discounts, which are spread over both.
     *
     * The same decomposition {@see BuildAttestationData}
     * prints on the mutual insurers' forms: amount due = licence + trainings
     * − family credit − discounts.
     */
    private function fileSubscription(Subscription $subscription, int $moneyCents, int $month): void
    {
        $licence = $this->cents((float) $subscription->subscription_price);
        $discounts = $this->cents((float) $subscription->discounts->sum('amount'));
        $trainings = $this->cents((float) $subscription->amount_due) - $licence + $this->cents((float) $subscription->family_credit) + $discounts;
        $gross = $licence + max(0, $trainings);

        $fees = $gross <= 0 ? $moneyCents : (int) round($moneyCents * $licence / $gross);

        $this->fileUnder(IncomeCategory::MembershipFees, $fees, $month, (bool) $subscription->is_competitive);
        $this->fileUnder(IncomeCategory::Trainings, $moneyCents - $fees, $month);
    }

    /**
     * Money that fits no poste yet: income when it came in, an expense when
     * it went out.
     */
    private function fileUncategorised(int $moneyCents, int $month): void
    {
        $this->file($moneyCents > 0 ? 'income' : 'expense', self::UNCATEGORISED, $moneyCents, $month);
    }

    /**
     * File money under a category, whichever direction the category reads.
     */
    private function fileUnder(ExpenseCategory|IncomeCategory $category, int $moneyCents, int $month, ?bool $competitive = null): void
    {
        $this->file($category instanceof ExpenseCategory ? 'expense' : 'income', $category->value, $moneyCents, $month, $competitive);
    }

    /**
     * Which fiscal month, 0 to 11, a day falls in.
     */
    private function monthOf(CarbonInterface $date): int
    {
        $start = $this->year->start();

        return ($date->year - $start->year) * 12 + $date->month - $start->month;
    }

    /**
     * @return array<string, \Closure(MorphTo<Model, Model>): MorphTo<Model, Model>>
     */
    private function payableWith(string $relation): array
    {
        return [$relation => static fn (MorphTo $morph): MorphTo => $morph->morphWith([
            Subscription::class => ['discounts'],
            Payment::class => ['payable'],
        ])];
    }

    private function readBankLines(): void
    {
        $lines = Transaction::query()
            ->whereDate('date', '>=', $this->year->start()->toDateString())
            ->whereDate('date', '<=', $this->year->end()->toDateString())
            ->with(['supportingDocuments', 'bankAccount', 'credits.payment', ...$this->payableWith('credits.payment.payable')])
            ->orderBy('date')
            ->orderBy('transactions.id')
            ->get();

        foreach ($lines as $line) {
            $money = $this->cents((float) $line->amount);
            $month = $this->monthOf($line->date);

            if ($line->is_internal) {
                $this->record(MovementClosure::Internal, $money);
                $this->internalMovements[] = [
                    'date' => CarbonImmutable::parse($line->date),
                    'source' => $line->bankAccount?->name ?? __('Bank'),
                    'description' => (string) $line->description,
                    'amount' => $this->euros($money),
                ];

                continue;
            }

            if ($line->supportingDocuments->isNotEmpty()) {
                $this->record(MovementClosure::Justified, $money);

                foreach ($line->categoryShares() as $share) {
                    $this->fileUnder($share['category'], $this->cents($share['amount']), $month);
                }

                continue;
            }

            $sign = $money < 0 ? -1 : 1;
            $allocated = 0;

            foreach ($line->credits as $credit) {
                /** @var PaymentCredit $credit */
                $creditMoney = $sign * abs($this->cents((float) $credit->amount));
                $allocated += $creditMoney;
                $this->fileSiteMoney($credit->payment?->payable, $creditMoney, $month);
            }

            $residue = $money - $allocated;

            if ($residue === 0 && $line->credits->isNotEmpty()) {
                $this->record(MovementClosure::Reconciled, $money);

                continue;
            }

            if ($line->settled_at !== null) {
                $this->record(MovementClosure::WrittenOff, $money);
                $this->fileUnder($residue > 0 ? IncomeCategory::Other : ExpenseCategory::Other, $residue, $month);

                continue;
            }

            $this->record(MovementClosure::ToProcess, $money);
            $this->fileUncategorised($residue, $month);
        }
    }

    private function readCashMovements(): void
    {
        $entries = CashRegisterEntry::query()
            ->whereDate('created_at', '>=', $this->year->start()->toDateString())
            ->whereDate('created_at', '<=', $this->year->end()->toDateString())
            ->with(['supportingDocuments', 'cashRegister' => fn ($query) => $query->withTrashed(), ...$this->payableWith('payable')])
            ->orderBy('created_at')
            ->orderBy('cash_register_entries.id')
            ->get();

        foreach ($entries as $entry) {
            $money = (int) $entry->amount;
            /** @var CarbonInterface $recordedAt */
            $recordedAt = $entry->created_at;
            $month = $this->monthOf($recordedAt);

            if ($entry->isInternal()) {
                $this->record(MovementClosure::Internal, $money);
                $this->internalMovements[] = [
                    'date' => CarbonImmutable::parse($recordedAt->toDateString()),
                    'source' => $entry->cashRegister->name ?? __('Cash register'),
                    'description' => (string) $entry->reason,
                    'amount' => $this->euros($money),
                ];

                continue;
            }

            if ($entry->payable_type !== null) {
                $this->record(MovementClosure::Reconciled, $money);
                $this->fileSiteMoney($entry->payable, $money, $month);

                continue;
            }

            if ($entry->supportingDocuments->isNotEmpty()) {
                $this->record(MovementClosure::Justified, $money);

                foreach ($entry->categoryShares() as $share) {
                    $this->fileUnder($share['category'], $this->cents($share['amount']), $month);
                }

                continue;
            }

            $this->record(MovementClosure::ToProcess, $money);
            $this->fileUncategorised($money, $month);
        }
    }

    private function record(MovementClosure $closure, int $moneyCents): void
    {
        $this->movements[] = ['closure' => $closure, 'cents' => abs($moneyCents)];
    }

    /**
     * @param  callable(array{poste: string, cents: int, month: int, competitive: bool|null}): bool  $filter
     */
    private function sumWhere(callable $filter): int
    {
        return array_sum(array_map(static fn (array $flow): int => $flow['cents'], array_filter($this->flows, $filter)));
    }
}
