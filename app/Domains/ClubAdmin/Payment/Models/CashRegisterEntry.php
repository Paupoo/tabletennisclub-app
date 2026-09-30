<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One movement of the club's cash: money in (positive) or out (negative), in
 * cents. Its date is `created_at`.
 *
 * Three kinds, never mixed: money the website accounts for (a `payable`), money
 * a supporting document justifies, and money moving between the till and the
 * bank (`transaction_id`, internal — neither income nor expense).
 *
 * @property int $id
 * @property int $cash_register_id
 * @property int $amount Signed, in cents: positive when cash comes in.
 * @property string $reason
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property int|null $transaction_id The bank line this movement deposited or withdrew, making it internal.
 * @property int $recorded_by_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CashRegister $cashRegister
 * @property-read Model|\Eloquent|null $payable
 * @property-read User $recordedBy
 * @property-read Transaction|null $transaction
 * @property-read Collection<int, SupportingDocument> $supportingDocuments
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry internal()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry external()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereCashRegisterId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry wherePayableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry wherePayableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereRecordedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CashRegisterEntry whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class CashRegisterEntry extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'amount' => 'integer',
    ];

    protected $fillable = [
        'cash_register_id',
        'amount',
        'reason',
        'payable_type',
        'payable_id',
        'recorded_by_id',
        'notes',
    ];

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * What this movement paid or brought in, category by category, pro rata
     * of the documents that justify it. Empty when none does. In euros,
     * signed like the movement.
     *
     * @return list<array{category: ExpenseCategory|IncomeCategory, amount: float}>
     */
    public function categoryShares(): array
    {
        return SupportingDocument::splitAcrossCategories($this->justifyingDocuments(), round($this->amount / 100, 2));
    }

    /**
     * Money the website accounts for, a supporting document justifies, or
     * that only moved between the till and the bank: either way, nothing is
     * left to explain.
     */
    public function isClosed(): bool
    {
        return $this->payable_type !== null || $this->isInternal() || $this->isJustified();
    }

    public function isIncoming(): bool
    {
        return $this->amount > 0;
    }

    /**
     * Money moving between the till and the bank: neither income nor expense.
     */
    public function isInternal(): bool
    {
        return $this->transaction_id !== null;
    }

    public function isJustified(): bool
    {
        return $this->justifyingDocuments()->isNotEmpty();
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * Not a till ↔ bank movement: what the report counts as income or expense.
     *
     * @param  Builder<CashRegisterEntry>  $query
     * @return Builder<CashRegisterEntry>
     */
    public function scopeExternal(Builder $query): Builder
    {
        return $query->whereNull('cash_register_entries.transaction_id');
    }

    /**
     * Money moving between the till and the bank.
     *
     * @param  Builder<CashRegisterEntry>  $query
     * @return Builder<CashRegisterEntry>
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->whereNotNull('cash_register_entries.transaction_id');
    }

    /**
     * @return BelongsToMany<SupportingDocument, $this>
     */
    public function supportingDocuments(): BelongsToMany
    {
        return $this->belongsToMany(SupportingDocument::class, 'cash_register_entry_supporting_document')->withTimestamps();
    }

    /**
     * The bank line this movement was deposited to, or withdrawn from.
     *
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return Collection<int, SupportingDocument>
     */
    private function justifyingDocuments(): Collection
    {
        return $this->relationLoaded('supportingDocuments') ? $this->supportingDocuments : $this->supportingDocuments()->get();
    }
}
