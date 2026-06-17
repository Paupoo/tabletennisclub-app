<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read BarOrder|null $order
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarPayment query()
 *
 * @mixin \Eloquent
 */
class BarPayment extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'order_id',
        'amount',
        'payment_method',
    ];

    protected $table = 'bar_payments';

    /**
     * ✅ A payment belongs to an order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(BarOrder::class, 'order_id');
    }
}
