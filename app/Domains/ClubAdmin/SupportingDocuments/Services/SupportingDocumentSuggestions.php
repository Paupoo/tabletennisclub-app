<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Services;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What a document and a movement of money could be to each other, in both
 * directions: the documents a bank line or a cash movement may be justified
 * by, and the movements that may have paid a document.
 *
 * A suggestion goes the same way (an expense document for money going out),
 * carries the same amount to the cent and lies within
 * {@see SupportingDocument::SUGGESTION_WINDOW_DAYS} days. Those whose
 * counterparty appears on the movement come first, then the closest in time.
 * Never linked automatically: the treasurer always clicks. The search is
 * there for everything the rule does not catch — an invoice paid in two
 * goes, a debit paying three invoices.
 */
final class SupportingDocumentSuggestions
{
    /** How many results a search shows: past that, the treasurer types more. */
    private const int SEARCH_LIMIT = 20;

    /**
     * Movements of the till a document may have been paid with: free of any
     * payable, deposit or document.
     *
     * @return Collection<int, CashRegisterEntry>
     */
    public function cashEntriesFor(SupportingDocument $document): Collection
    {
        $cents = (int) round($document->amount * 100);

        return CashRegisterEntry::query()
            ->with('cashRegister')
            ->whereNull('payable_type')
            ->whereNull('transaction_id')
            ->whereDoesntHave('supportingDocuments')
            ->where('amount', $document->isExpense() ? -$cents : $cents)
            ->whereBetween('created_at', $this->window($document->date, withTime: true))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (CashRegisterEntry $entry): int => abs((int) $entry->created_at?->diffInDays($document->date)))
            ->values();
    }

    /**
     * Bank lines a movement of the till may have gone to or come from: the
     * same money the other way round, within the window, on a line nothing
     * else explains yet.
     *
     * @return Collection<int, Transaction>
     */
    public function bankLinesForDeposit(CashRegisterEntry $entry): Collection
    {
        $date = $entry->created_at ?? now();

        return $this->justifiable()
            ->whereDoesntHave('supportingDocuments')
            ->whereDoesntHave('cashRegisterEntry')
            ->where('amount', -$entry->amount)
            ->whereDate('date', '>=', $this->window($date)[0])
            ->whereDate('date', '<=', $this->window($date)[1])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Transaction $transaction): int => abs((int) $transaction->date->diffInDays($date)))
            ->values();
    }

    /**
     * Does the document's counterparty appear on the bank line?
     */
    public function counterpartyMatches(SupportingDocument $document, Transaction $transaction): bool
    {
        $needle = mb_strtolower(trim($document->counterparty));

        if (mb_strlen($needle) < 3) {
            return false;
        }

        return str_contains(mb_strtolower($transaction->counterparty_name . ' ' . $transaction->description), $needle);
    }

    /**
     * Documents still to settle that a bank line or a cash movement may pay.
     *
     * @return Collection<int, SupportingDocument>
     */
    public function documentsFor(Transaction|CashRegisterEntry $movement): Collection
    {
        [$cents, $date] = $this->amountAndDateOf($movement);

        $documents = SupportingDocument::query()
            ->with('files')
            ->toSettle()
            ->where('amount', abs($cents))
            ->when($cents < 0, fn (Builder $q): Builder => $q->expenses(), fn (Builder $q): Builder => $q->incomes())
            ->whereDate('date', '>=', $this->window($date)[0])
            ->whereDate('date', '<=', $this->window($date)[1])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return $documents
            ->sortBy(fn (SupportingDocument $document): array => [
                $movement instanceof Transaction && $this->counterpartyMatches($document, $movement) ? 0 : 1,
                abs((int) $document->date->diffInDays($date)),
            ])
            ->values();
    }

    /**
     * Any document, by reference, counterparty, label or amount.
     *
     * The cash register délégation only ever sees documents that justify no
     * bank line: those are the treasury's.
     *
     * @param  list<int>  $exceptIds
     * @return Collection<int, SupportingDocument>
     */
    public function searchDocuments(string $search, array $exceptIds = [], bool $withoutBankLines = false): Collection
    {
        $needle = trim($search);

        if ($needle === '') {
            return new Collection;
        }

        return SupportingDocument::query()
            ->with('files')
            ->whereKeyNot($exceptIds)
            ->when($withoutBankLines, fn (Builder $q): Builder => $q->whereDoesntHave('transactions'))
            ->where(function (Builder $q) use ($needle): void {
                $q->where('counterparty', 'like', "%{$needle}%")
                    ->orWhere('label', 'like', "%{$needle}%");

                if (preg_match('/^P-\d{4}-0*(\d+)$/i', $needle, $reference) === 1) {
                    $q->orWhere('id', (int) $reference[1]);
                }

                if (is_numeric(str_replace(',', '.', $needle))) {
                    $q->orWhere('amount', (int) round((float) str_replace(',', '.', $needle) * 100));
                }
            })
            ->orderByDesc('date')
            ->orderBy('id')
            ->limit(self::SEARCH_LIMIT)
            ->get();
    }

    /**
     * Bank lines a document may be linked to, by counterparty, description or
     * amount — only those a document may justify at all.
     *
     * @param  list<int>  $exceptIds
     * @return Collection<int, Transaction>
     */
    public function searchTransactions(string $search, array $exceptIds = []): Collection
    {
        $needle = trim($search);

        if ($needle === '') {
            return new Collection;
        }

        return $this->justifiable()
            ->whereKeyNot($exceptIds)
            ->where(function (Builder $q) use ($needle): void {
                $q->where('counterparty_name', 'like', "%{$needle}%")
                    ->orWhere('description', 'like', "%{$needle}%")
                    ->orWhere('free_reference', 'like', "%{$needle}%");

                if (is_numeric(str_replace(',', '.', $needle))) {
                    $cents = (int) round((float) str_replace(',', '.', $needle) * 100);
                    $q->orWhereIn('amount', [$cents, -$cents]);
                }
            })
            ->orderByDesc('date')
            ->orderBy('id')
            ->limit(self::SEARCH_LIMIT)
            ->get();
    }

    /**
     * Bank lines not justified yet that may have paid a document.
     *
     * @return Collection<int, Transaction>
     */
    public function transactionsFor(SupportingDocument $document): Collection
    {
        $cents = (int) round($document->amount * 100);

        return $this->justifiable()
            ->whereDoesntHave('supportingDocuments')
            ->where('amount', $document->isExpense() ? -$cents : $cents)
            ->whereDate('date', '>=', $this->window($document->date)[0])
            ->whereDate('date', '<=', $this->window($document->date)[1])
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Transaction $transaction): array => [
                $this->counterpartyMatches($document, $transaction) ? 0 : 1,
                abs((int) $transaction->date->diffInDays($document->date)),
            ])
            ->values();
    }

    /**
     * @return array{0: int, 1: CarbonInterface}
     */
    private function amountAndDateOf(Transaction|CashRegisterEntry $movement): array
    {
        if ($movement instanceof Transaction) {
            return [(int) round((float) $movement->amount * 100), $movement->date];
        }

        return [$movement->amount, $movement->created_at ?? now()];
    }

    /**
     * Lines a document may justify: not a single euro of the website's money
     * on them, and not a move between the club's own accounts.
     *
     * @return Builder<Transaction>
     */
    private function justifiable(): Builder
    {
        return Transaction::query()
            ->with('bankAccount')
            ->where('allocated_amount', 0)
            ->where('is_internal', false);
    }

    /**
     * The days around a date a suggestion may lie in: plain dates for a date
     * column (compared with `whereDate`, which SQLite and MySQL agree on),
     * whole days of timestamps for `created_at`.
     *
     * @return array{0: string, 1: string}
     */
    private function window(CarbonInterface $date, bool $withTime = false): array
    {
        $from = $date->copy()->subDays(SupportingDocument::SUGGESTION_WINDOW_DAYS)->startOfDay();
        $to = $date->copy()->addDays(SupportingDocument::SUGGESTION_WINDOW_DAYS)->endOfDay();

        return $withTime
            ? [$from->toDateTimeString(), $to->toDateTimeString()]
            : [$from->toDateString(), $to->toDateString()];
    }
}
