<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Payment\Services\TransactionMatch;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $date
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
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment|null $payment
 * @property-read Payment|null $refundPayment
 * @property-read BankImport|null $bankImport
 * @property TransactionMatch|null $match Verdict de rapprochement, posé à la volée par TransactionMatcher::rank() — jamais persisté.
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction unallocated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction partiallyAllocated()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction settled()
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
    ];

    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(BankImport::class);
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
     * Cette ligne de relevé est-elle close ?
     *
     * Deux façons de l'être, et elles se valent pour le trésorier : tout est
     * affecté, ou ce qui restait a été délibérément abandonné.
     */
    public function isSettled(): bool
    {
        return $this->settled_at !== null || $this->residueInCents() === 0;
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
     * Une partie a trouvé son paiement, le reste attend.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopePartiallyAllocated(Builder $query): Builder
    {
        return $query->whereNull('settled_at')
            ->where('allocated_amount', '!=', 0)
            ->whereColumn('allocated_amount', '!=', 'amount');
    }

    /**
     * Close : tout est affecté, ou ce qui restait a été délibérément abandonné.
     *
     * Le groupe autour du `OR` n'est pas décoratif — à plat, il s'évaderait des
     * filtres de date et de recherche que l'écran applique autour.
     *
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q
            ->whereColumn('allocated_amount', 'amount')
            ->orWhereNotNull('settled_at'));
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
        return $query->where('allocated_amount', 0)->whereNull('settled_at');
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

    /**
     * En centimes : deux flottants qui devraient être égaux ne le sont pas
     * toujours, et une ligne soldée à un millième près resterait ouverte.
     */
    private function residueInCents(): int
    {
        return (int) round(((float) $this->amount - (float) $this->allocated_amount) * 100);
    }
}
