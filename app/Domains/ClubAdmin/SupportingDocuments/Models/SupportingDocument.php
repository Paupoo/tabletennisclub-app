<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\SupportingDocuments\Models;

use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\SupportingDocumentState;
use App\Domains\Shared\Traits\HasAuditLog;
use App\Domains\Shared\ValueObjects\FiscalYear;
use Database\Factories\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An invoice, a ticket, a subsidy letter: the proof behind money the website
 * never saw.
 *
 * Linked without any amount to the bank lines and cash register movements that
 * paid it. Its state is read off those links and never stored: no link, the
 * club still owes it (an expense) or is still owed it (an income); one link or
 * more, it is settled. A bank line linked to a document is closed, the third
 * way next to a full allocation and a written-off residue.
 *
 * Exactly one of `expense_category` and `income_category` is set; the
 * direction of the money follows from which one.
 *
 * @property int $id
 * @property Carbon $date The date printed on the document.
 * @property float $amount Positive, VAT included; stored in cents.
 * @property ExpenseCategory|null $expense_category
 * @property IncomeCategory|null $income_category
 * @property string $counterparty
 * @property string $label
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, SupportingDocumentFile> $files
 * @property-read Collection<int, Transaction> $transactions
 * @property-read Collection<int, CashRegisterEntry> $cashRegisterEntries
 * @property-read User|null $createdBy
 *
 * @method static SupportingDocumentFactory factory($count = null, $state = [])
 * @method static Builder<static>|SupportingDocument toSettle()
 * @method static Builder<static>|SupportingDocument settled()
 * @method static Builder<static>|SupportingDocument expenses()
 * @method static Builder<static>|SupportingDocument incomes()
 * @method static Builder<static>|SupportingDocument datedIn(FiscalYear $year)
 * @method static Builder<static>|SupportingDocument inCategory(string $categoryKey)
 *
 * @mixin \Eloquent
 */
class SupportingDocument extends Model
{
    use HasAuditLog;

    /** @use HasFactory<SupportingDocumentFactory> */
    use HasFactory;

    use SoftDeletes;

    /** How far apart, in days, a document and the money that paid it may be to be suggested together. */
    public const int SUGGESTION_WINDOW_DAYS = 45;

    protected $casts = [
        'date' => 'date',
        'expense_category' => ExpenseCategory::class,
        'income_category' => IncomeCategory::class,
    ];

    protected $fillable = [
        'date',
        'amount',
        'expense_category',
        'income_category',
        'counterparty',
        'label',
        'created_by_id',
    ];

    /**
     * The category a form key names: `expense:hall`, `income:subsidies`.
     */
    public static function categoryFromKey(string $key): ExpenseCategory|IncomeCategory|null
    {
        [$direction, $value] = array_pad(explode(':', $key, 2), 2, '');

        return match ($direction) {
            'expense' => ExpenseCategory::tryFrom($value),
            'income' => IncomeCategory::tryFrom($value),
            default => null,
        };
    }

    /**
     * The categories a document may be filed under, grouped by direction, for
     * a select input: every expense, and every income the website does not
     * already account for.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function categoryOptions(): array
    {
        return [
            ...array_map(
                fn (array $option): array => ['id' => 'expense:' . $option['id'], 'name' => __('Money out') . ' — ' . $option['name']],
                ExpenseCategory::getOptions(),
            ),
            ...array_map(
                fn (IncomeCategory $category): array => ['id' => 'income:' . $category->value, 'name' => __('Money in') . ' — ' . $category->label()],
                IncomeCategory::forDocuments(),
            ),
        ];
    }

    public static function keyOf(ExpenseCategory|IncomeCategory $category): string
    {
        return ($category instanceof ExpenseCategory ? 'expense:' : 'income:') . $category->value;
    }

    /**
     * Share an amount of money out between the categories of the documents
     * that justify it, pro rata of the documents' amounts.
     *
     * A debit that paid a hall invoice of 300 € and a ball order of 100 € is
     * 75 % hall and 25 % sports equipment. Rounded to the cent, the last share
     * taking the rounding so the shares always add up to the amount.
     *
     * @param  iterable<self>  $documents
     * @return list<array{category: ExpenseCategory|IncomeCategory, amount: float}>
     */
    public static function splitAcrossCategories(iterable $documents, float $amount): array
    {
        $weights = [];

        foreach ($documents as $document) {
            $key = self::keyOf($document->category());
            $weights[$key] = ($weights[$key] ?? 0) + (int) round($document->amount * 100);
        }

        $total = array_sum($weights);
        $amountInCents = (int) round($amount * 100);

        if ($total === 0) {
            return [];
        }

        $shares = [];
        $given = 0;
        $keys = array_keys($weights);

        foreach ($keys as $index => $key) {
            $share = $index === count($keys) - 1
                ? $amountInCents - $given
                : intdiv($amountInCents * $weights[$key], $total);
            $given += $share;

            /** @var ExpenseCategory|IncomeCategory $category */
            $category = self::categoryFromKey($key);
            $shares[] = ['category' => $category, 'amount' => round($share / 100, 2)];
        }

        return $shares;
    }

    /**
     * The movements of cash that paid (or brought in) this document.
     *
     * @return BelongsToMany<CashRegisterEntry, $this>
     */
    public function cashRegisterEntries(): BelongsToMany
    {
        return $this->belongsToMany(CashRegisterEntry::class, 'cash_register_entry_supporting_document')->withTimestamps();
    }

    public function category(): ExpenseCategory|IncomeCategory
    {
        return $this->expense_category ?? $this->income_category;
    }

    public function categoryKey(): string
    {
        return self::keyOf($this->category());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return HasMany<SupportingDocumentFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(SupportingDocumentFile::class);
    }

    /**
     * The linked movements add up to something else than the document.
     *
     * A warning, never a block: an invoice paid in two goes is settled only
     * once both are linked, and a debit may pay several invoices at once.
     */
    public function hasAmountMismatch(): bool
    {
        return $this->isSettled() && (int) round($this->linkedAmount() * 100) !== (int) round($this->amount * 100);
    }

    public function isExpense(): bool
    {
        return $this->expense_category !== null;
    }

    public function isIncome(): bool
    {
        return $this->income_category !== null;
    }

    /**
     * Linked to at least one movement. Only a document linked to nothing may
     * be deleted.
     */
    public function isSettled(): bool
    {
        return $this->linkedTransactions()->isNotEmpty() || $this->linkedCashRegisterEntries()->isNotEmpty();
    }

    /**
     * What the linked bank lines and cash movements add up to, in euros,
     * always positive.
     */
    public function linkedAmount(): float
    {
        $cents = $this->linkedTransactions()->sum(fn (Transaction $transaction): int => abs((int) round($transaction->amount * 100)))
            + $this->linkedCashRegisterEntries()->sum(fn (CashRegisterEntry $entry): int => abs($entry->amount));

        return round($cents / 100, 2);
    }

    /**
     * « P-2026-0042 »: the year the document is dated, then its number.
     */
    public function reference(): string
    {
        return sprintf('P-%d-%04d', $this->date->year, $this->id);
    }

    /**
     * Dated within a financial year — the date printed on the document, not
     * the day it was paid: the report counts money at the date it moved,
     * through the linked movements.
     *
     * @param  Builder<SupportingDocument>  $query
     * @return Builder<SupportingDocument>
     */
    public function scopeDatedIn(Builder $query, FiscalYear $year): Builder
    {
        return $query->whereDate('supporting_documents.date', '>=', $year->start()->toDateString())
            ->whereDate('supporting_documents.date', '<=', $year->end()->toDateString());
    }

    /**
     * @param  Builder<SupportingDocument>  $query
     * @return Builder<SupportingDocument>
     */
    public function scopeExpenses(Builder $query): Builder
    {
        return $query->whereNotNull('expense_category');
    }

    /**
     * Filed under the category a form key names (`expense:hall`).
     *
     * @param  Builder<SupportingDocument>  $query
     * @return Builder<SupportingDocument>
     */
    public function scopeInCategory(Builder $query, string $categoryKey): Builder
    {
        $category = self::categoryFromKey($categoryKey);

        return match (true) {
            $category instanceof ExpenseCategory => $query->where('expense_category', $category->value),
            $category instanceof IncomeCategory => $query->where('income_category', $category->value),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @param  Builder<SupportingDocument>  $query
     * @return Builder<SupportingDocument>
     */
    public function scopeIncomes(Builder $query): Builder
    {
        return $query->whereNotNull('income_category');
    }

    /**
     * Linked to at least one bank line or cash movement.
     *
     * @param  Builder<SupportingDocument>  $query
     * @return Builder<SupportingDocument>
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q
            ->whereHas('transactions')
            ->orWhereHas('cashRegisterEntries'));
    }

    /**
     * Linked to nothing yet: an open debt when it is an expense, an open
     * receivable when it is an income.
     *
     * @param  Builder<SupportingDocument>  $query
     * @return Builder<SupportingDocument>
     */
    public function scopeToSettle(Builder $query): Builder
    {
        return $query->whereDoesntHave('transactions')->whereDoesntHave('cashRegisterEntries');
    }

    public function state(): SupportingDocumentState
    {
        return $this->isSettled() ? SupportingDocumentState::Settled : SupportingDocumentState::ToSettle;
    }

    /**
     * @return BelongsToMany<Transaction, $this>
     */
    public function transactions(): BelongsToMany
    {
        return $this->belongsToMany(Transaction::class, 'supporting_document_transaction')->withTimestamps();
    }

    /** Amount stored in cents, exposed as euros. */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            set: fn (int|float|string $value): int => (int) round((float) $value * 100),
        );
    }

    /**
     * @return Collection<int, CashRegisterEntry>
     */
    private function linkedCashRegisterEntries(): Collection
    {
        return $this->relationLoaded('cashRegisterEntries') ? $this->cashRegisterEntries : $this->cashRegisterEntries()->get();
    }

    /**
     * @return Collection<int, Transaction>
     */
    private function linkedTransactions(): Collection
    {
        return $this->relationLoaded('transactions') ? $this->transactions : $this->transactions()->get();
    }
}
