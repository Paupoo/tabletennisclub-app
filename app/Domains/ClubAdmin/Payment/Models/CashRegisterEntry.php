<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Subscriptions\Models\Registration;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingUser;
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
 * @property string|null $category An income or an expense category value, when the movement itself names its poste.
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

    /**
     * The reason of the movement a count writes when the drawer and the books
     * disagree.
     */
    public const string DIFFERENCE_REASON = 'cash_difference';

    /**
     * The reason of the movement declared as what the drawer held before the
     * app followed it.
     */
    public const string OPENING_REASON = 'opening_balance';

    protected $casts = [
        'amount' => 'integer',
    ];

    protected $fillable = [
        'cash_register_id',
        'amount',
        'reason',
        'category',
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
     * The poste the movement itself names — a count's difference, already
     * spread — or null when its payable or its documents say it.
     */
    public function filedCategory(): ExpenseCategory|IncomeCategory|null
    {
        if ($this->category === null) {
            return null;
        }

        return IncomeCategory::tryFrom($this->category) ?? ExpenseCategory::tryFrom($this->category);
    }

    /**
     * The cash this movement brought in, income category by income category,
     * in cents — what a count's shortfall is spread over. Empty for money
     * that went out, moved to or from the bank, or that nobody explained yet.
     *
     * Read the way the financial report files it, except an affiliation paid
     * in cash, which counts as membership fees whole instead of splitting with
     * the trainings: the report's split needs the affiliation's composition,
     * and a cash affiliation is rare enough at the till.
     *
     * @return array<string, int> keyed by income category value
     */
    public function incomeShares(): array
    {
        if ($this->amount <= 0 || $this->isInternal() || $this->isOpeningBalance() || $this->reason === self::DIFFERENCE_REASON) {
            return [];
        }

        $category = $this->filedCategory() ?? self::incomeCategoryOf($this->payable);

        if ($category instanceof IncomeCategory) {
            return [$category->value => $this->amount];
        }

        $shares = [];

        foreach ($this->categoryShares() as $share) {
            if ($share['category'] instanceof IncomeCategory) {
                $shares[$share['category']->value] = ($shares[$share['category']->value] ?? 0) + (int) round($share['amount'] * 100);
            }
        }

        return $shares;
    }

    /**
     * Money the website accounts for, a supporting document justifies, a
     * count filed, the till's opening balance, or that only moved between the
     * till and the bank: either way, nothing is left to explain.
     */
    public function isClosed(): bool
    {
        return $this->payable_type !== null
            || $this->category !== null
            || $this->isOpeningBalance()
            || $this->isInternal()
            || $this->isJustified();
    }

    /**
     * No movement of the same till came before this one.
     */
    public function isFirstOfItsTill(): bool
    {
        return ! self::query()
            ->where('cash_register_id', $this->cash_register_id)
            ->whereKeyNot($this->getKey())
            ->where(fn (Builder $query): Builder => $query
                ->where('created_at', '<', $this->created_at)
                ->orWhere(fn (Builder $sameSecond): Builder => $sameSecond
                    ->where('created_at', $this->created_at)
                    ->where('id', '<', $this->getKey())))
            ->exists();
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

    /**
     * What the drawer held before the app followed it: neither income nor a
     * transfer from the bank.
     */
    public function isOpeningBalance(): bool
    {
        return $this->reason === self::OPENING_REASON;
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
     * The income a payment the website accounts for brought in.
     */
    private static function incomeCategoryOf(?Model $payable): ?IncomeCategory
    {
        return match (true) {
            $payable instanceof Payment => self::incomeCategoryOf($payable->payable),
            $payable instanceof BarOrder => IncomeCategory::Bar,
            $payable instanceof Subscription => IncomeCategory::MembershipFees,
            $payable instanceof SubscriptionTrainingPack,
            $payable instanceof ExternalRegistration => IncomeCategory::Trainings,
            $payable instanceof TournamentRegistration,
            $payable instanceof Tournament,
            $payable instanceof MeetingUser,
            $payable instanceof Meeting,
            $payable instanceof Registration => IncomeCategory::Event,
            default => null,
        };
    }

    /**
     * @return Collection<int, SupportingDocument>
     */
    private function justifyingDocuments(): Collection
    {
        return $this->relationLoaded('supportingDocuments') ? $this->supportingDocuments : $this->supportingDocuments()->get();
    }
}
