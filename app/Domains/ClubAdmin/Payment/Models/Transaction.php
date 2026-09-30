<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Payment\Services\TransactionMatch;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property string $description
 * @property float $amount
 * @property float $allocated_amount
 * @property Carbon|null $settled_at
 * @property string|null $settled_reason
 * @property int|null $settled_by_id
 * @property string|null $counterparty_name
 * @property string|null $counterparty_bank_account
 * @property string|null $structured_reference
 * @property string|null $free_reference
 * @property string|null $import_fingerprint
 * @property int|null $bank_import_id
 * @property int|null $bank_account_id
 * @property float|null $balance_after Account balance right after this line, in euros, as the bank printed it.
 * @property string|null $statement_number Without the bank's zero padding.
 * @property bool $is_internal Money moving between the club's own accounts (or, later, its till).
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment|null $payment
 * @property-read Payment|null $refundPayment
 * @property-read BankImport|null $bankImport
 * @property-read BankAccount|null $bankAccount
 * @property-read Collection<int, SupportingDocument> $supportingDocuments
 * @property-read CashRegisterEntry|null $cashRegisterEntry The till movement this line deposited or withdrew, when it is one.
 * @property TransactionMatch|null $match Verdict de rapprochement, posé à la volée par TransactionMatcher::rank() — jamais persisté.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction unallocated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction partiallyAllocated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction settled()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction internal()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction justified()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction reconcilable()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Transaction extends Model
{
    use HasAuditLog;
    use HasFactory, SoftDeletes;

    protected $casts = [
        'date' => 'date',
        'settled_at' => 'datetime',
        'is_internal' => 'boolean',
    ];

    protected $fillable = [
        'date',
        'description',
        'amount',
        'counterparty_name',
        'counterparty_bank_account',
        'structured_reference',
        'free_reference',
        'import_fingerprint',
        'bank_import_id',
        'bank_account_id',
        'balance_after',
        'statement_number',
        'is_internal',
    ];

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(BankImport::class);
    }

    /**
     * The till movement this line brought to the bank or took from it, when
     * the treasurer linked them: money moving between the till and the bank.
     *
     * @return HasOne<CashRegisterEntry, $this>
     */
    public function cashRegisterEntry(): HasOne
    {
        return $this->hasOne(CashRegisterEntry::class);
    }

    /**
     * What this line paid or brought in, category by category: the documents
     * that justify it share its amount pro rata of their own amounts.
     *
     * Empty when no document justifies the line. Signed like the line.
     *
     * @return list<array{category: ExpenseCategory|IncomeCategory, amount: float}>
     */
    public function categoryShares(): array
    {
        return SupportingDocument::splitAcrossCategories($this->justifyingDocuments(), (float) $this->amount);
    }

    /**
     * Les affectations de cette ligne de relevé.
     *
     * @return HasMany<PaymentCredit, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(PaymentCredit::class);
    }

    /**
     * Closed by a supporting document: money the website never saw, an
     * invoice or a subsidy letter that says what it was.
     */
    public function isJustified(): bool
    {
        return $this->justifyingDocuments()->isNotEmpty();
    }

    /**
     * Cette ligne de relevé est-elle close ?
     *
     * Quatre façons de l'être, et elles se valent pour le trésorier : tout est
     * affecté, ce qui restait a été délibérément abandonné, l'argent n'a
     * fait que passer d'un compte du club à un autre, ou une pièce
     * justificative dit ce qu'il était.
     */
    public function isSettled(): bool
    {
        return $this->is_internal || $this->settled_at !== null || $this->residueInCents() === 0 || $this->isJustified();
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'transaction_id');
    }

    public function refundPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'refund_transaction_id');
    }

    /**
     * Ce qui reste à affecter sur cette ligne, en euros, du signe du montant.
     */
    public function residue(): float
    {
        return round($this->residueInCents() / 100, 2);
    }

    /**
     * Mouvement interne : l'argent passe d'un compte du club à un autre.
     *
     * Ni recette ni dépense, et personne ne l'a payé : la ligne est close dès
     * l'import. Posé aujourd'hui quand la contrepartie est un autre compte du
     * club ; un versement de la caisse vers la banque (ou l'inverse) le sera
     * au même titre, par le geste qui relie la ligne à l'écriture de caisse.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeInternal(Builder $query): Builder
    {
        return $query->where('is_internal', true);
    }

    /**
     * Closed by at least one supporting document.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeJustified(Builder $query): Builder
    {
        return $query->whereHas('supportingDocuments');
    }

    /**
     * Une partie a trouvé son paiement, le reste attend.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopePartiallyAllocated(Builder $query): Builder
    {
        return $query->whereNull('settled_at')
            ->where('is_internal', false)
            ->where('allocated_amount', '!=', 0)
            ->whereColumn('allocated_amount', '!=', 'amount')
            ->whereDoesntHave('supportingDocuments');
    }

    /**
     * Ce que le rapprochement automatique a le droit de regarder : une ligne
     * d'un compte courant — ou d'un relevé sans compte, d'avant qu'il y en ait
     * plusieurs —, qui n'est pas un mouvement interne.
     *
     * Un compte d'épargne ne reçoit jamais le virement d'un membre ; lui
     * proposer des créances, c'est inviter le trésorier à se tromper. Une
     * ligne justifiée par une pièce non plus : c'est de l'argent hors site, et
     * mêler les deux sur une ligne est interdit.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeReconcilable(Builder $query): Builder
    {
        return $query
            ->where('is_internal', false)
            ->whereDoesntHave('supportingDocuments')
            ->where(fn (Builder $q): Builder => $q
                ->whereNull('bank_account_id')
                ->orWhereHas('bankAccount', fn (Builder $account): Builder => $account->where('type', BankAccountType::Current->value)));
    }

    /**
     * Close : tout est affecté, ce qui restait a été délibérément abandonné,
     * c'est un mouvement interne, ou une pièce la justifie.
     *
     * Le groupe autour des `OR` n'est pas décoratif — à plat, il s'évaderait
     * des filtres de date et de recherche que l'écran applique autour. Une
     * nouvelle façon d'être close s'ajoute **dans** ce groupe.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q
            ->whereColumn('allocated_amount', 'amount')
            ->orWhereNotNull('settled_at')
            ->orWhere('is_internal', true)
            ->orWhereHas('supportingDocuments'));
    }

    /**
     * Rien n'a encore été placé sur cette ligne, et rien n'a été abandonné.
     *
     * Les débits en font partie : un virement sortant jamais rapproché est un
     * remboursement parti sans destinataire identifié, donc du travail. La
     * règle d'avant les écartait parce que `has('payment')` ne regardait que
     * `transaction_id` — les remboursements vivaient dans une autre colonne.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeUnallocated(Builder $query): Builder
    {
        return $query->where('allocated_amount', 0)
            ->whereNull('settled_at')
            ->where('is_internal', false)
            ->whereDoesntHave('supportingDocuments');
    }

    /**
     * The supporting documents that justify this line.
     *
     * @return BelongsToMany<SupportingDocument, $this>
     */
    public function supportingDocuments(): BelongsToMany
    {
        return $this->belongsToMany(SupportingDocument::class, 'supporting_document_transaction')->withTimestamps();
    }

    /**
     * Ce qui a déjà trouvé son paiement, en euros.
     *
     * Miroir des lignes de crédit, écrit par AllocateTransactionAction et par
     * personne d'autre — d'où son absence de `$fillable`.
     */
    protected function allocatedAmount(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    /** Amount stored in cents, exposed as euros. */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    /** Balance stored in cents, exposed as euros; unknown on lines imported before it was read. */
    protected function balanceAfter(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): ?float => $value === null ? null : round($value / 100, 2),
            set: fn (int|float|null $value): ?int => $value === null ? null : (int) round($value * 100),
        );
    }

    /**
     * @return Collection<int, SupportingDocument>
     */
    private function justifyingDocuments(): Collection
    {
        return $this->relationLoaded('supportingDocuments') ? $this->supportingDocuments : $this->supportingDocuments()->get();
    }

    /**
     * En centimes : deux flottants qui devraient être égaux ne le sont pas
     * toujours, et une ligne soldée à un millième près resterait ouverte.
     */
    private function residueInCents(): int
    {
        return (int) round(((float) $this->amount - (float) $this->allocated_amount) * 100);
    }
}
