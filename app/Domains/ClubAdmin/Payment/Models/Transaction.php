<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Payment\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $date
 * @property string $description
 * @property float $amount
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
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
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
    use HasFactory, SoftDeletes;

    protected $casts = [
        'date' => 'date',
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

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class, 'transaction_id');
    }

    public function refundPayment(): HasOne
    {
        return $this->hasOne(Payment::class, 'refund_transaction_id');
    }

    /** Amount stored in cents, exposed as euros. */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }
}
