<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Payment\Services\TransactionMatch;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property string|null $transaction_id
 * @property float $amount_due
 * @property float $amount_paid
 * @property string $status
 * @property string $payable_type
 * @property int $payable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $invitation_counter
 * @property Carbon|null $last_reminded_at
 * @property int|null $refund_transaction_id
 * @property string $payment_method
 * @property string|null $refund_iban
 * @property TransactionMatch|null $match Verdict de rapprochement, posé à la volée — jamais persisté.
 * @property-read Model|\Eloquent $payable
 * @property-read Transaction|null $refundTransaction
 *
 * @method static \Database\Factories\Domains\ClubAdmin\Payment\Models\PaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmountDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereInvitationCounter($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePayableId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePayableType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereRefundTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payment whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Payment extends Model
{
    use HasAuditLog;
    use HasFactory;

    protected $casts = [
        'amount_due' => 'integer',   // stocké en centimes
        'amount_paid' => 'integer',  // stocké en centimes
        'last_reminded_at' => 'datetime',
    ];

    protected $fillable = [
        'reference',
        'amount_due',
        'amount_paid',
        'status',
        'payment_method',
        'refund_iban',
        'transaction_id',
        'refund_transaction_id',
    ];

    /**
     * Les montants tolèrent l'absence, comme ceux de {@see Subscription}.
     *
     * Un `Payment` n'est pas toujours une ligne en base : le bar en construit
     * un transitoire, `amount_due` et une référence, pour afficher un QR au
     * client. `amount_paid` y est nul, et un type strict fait tomber la page
     * sur une valeur qui n'a jamais eu à exister.
     */
    public function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    public function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }

    /**
     * Ce qu'il reste à payer sur cette ligne, en euros.
     *
     * La seule chose qu'un membre ou un trésorier veut lire. `amount_due` est
     * ce qui a été réclamé au départ : depuis qu'un paiement peut être crédité
     * en plusieurs fois, les deux divergent, et afficher le premier revient à
     * réclamer une somme déjà reçue.
     *
     * Jamais négatif : un trop-perçu n'est pas une dette négative, c'est de
     * l'argent à rendre — et ça se dit ailleurs.
     */
    public function balance(): float
    {
        return max(0.0, round((float) $this->amount_due - (float) $this->amount_paid, 2));
    }

    /**
     * Les sommes encaissées sur ce paiement.
     *
     * @return HasMany<PaymentCredit, $this>
     */
    public function credits(): HasMany
    {
        return $this->hasMany(PaymentCredit::class);
    }

    public function isOverpaid(): bool
    {
        return $this->overpayment() > 0.0;
    }

    /** Une ligne partiellement créditée : de l'argent est entré, il en manque. */
    public function isPartiallyPaid(): bool
    {
        return (float) $this->amount_paid > 0.0 && $this->balance() > 0.0;
    }

    /**
     * Ce que le club détient en trop sur cette ligne, en euros.
     *
     * Le pendant de {@see balance()} : ce que les crédits dépassent du montant
     * dû, là où le solde est ce qu'il leur manque. Rien n'est stocké — un
     * trop-perçu est une position, pas un objet.
     *
     * Cet argent n'appartient plus au club. Il revient au **compte qui l'a
     * versé**, pas au membre : c'est celui-là qu'on rembourse.
     */
    public function overpayment(): float
    {
        return max(0.0, round((float) $this->amount_paid - (float) $this->amount_due, 2));
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function refundTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'refund_transaction_id');
    }
}
