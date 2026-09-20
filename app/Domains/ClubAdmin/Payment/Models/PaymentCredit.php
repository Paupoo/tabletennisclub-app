<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une somme encaissée sur un paiement.
 *
 * C'est la seule chose qui crédite un paiement : `payments.amount_paid` n'est
 * que la somme de ces lignes, et personne d'autre ne l'écrit.
 *
 * @property int $id
 * @property int $payment_id
 * @property int|null $transaction_id
 * @property float $amount
 * @property string|null $method
 * @property string|null $note
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Payment $payment
 * @property-read Transaction|null $transaction
 * @property-read User|null $createdBy
 *
 * @mixin \Eloquent
 */
class PaymentCredit extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'transaction_id',
        'amount',
        'method',
        'note',
        'created_by_id',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** Stocké en centimes, exposé en euros — comme {@see Payment}. */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }
}
