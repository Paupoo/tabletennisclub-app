<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $reference
 * @property string|null $transaction_id
 * @property float $amount_due
 * @property float $amount_paid
 * @property string $status
 * @property string $payable_type
 * @property int $payable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $invitation_counter
 * @property int|null $refund_transaction_id
 * @property string $payment_method
 * @property-read Model|\Eloquent $payable
 * @property-read \App\Domains\ClubAdmin\Payment\Models\Transaction|null $refundTransaction
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
 * @mixin \Eloquent
 */
class Payment extends Model
{
    use HasFactory;

    protected $casts = [
        'amount_due' => 'integer',   // stocké en centimes
        'amount_paid' => 'integer',  // stocké en centimes
    ];

    protected $fillable = [
        'reference',
        'amount_due',
        'amount_paid',
        'status',
        'payment_method',
        'transaction_id',
        'refund_transaction_id',
    ];

    // Accessor pour les prix
    public function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            // set: fn (int|float $value): int => (int) $value * 100,
            set: fn (int|float $value): int => (int) round(((float) $value) * 100),
        );
    }

    public function amountPaid(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            // set: fn (int|float $value): int => (int) $value * 100,
            set: fn (int|float $value): int => (int) round(((float) $value) * 100),
        );
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
