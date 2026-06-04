<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read \App\Domains\Bar\Models\BarOrder|null $order
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarPayment query()
 * @mixin \Eloquent
 */
class BarPayment extends Model
{
    protected $table = 'bar_payments';

    protected $fillable = [
        'order_id',
        'amount',
        'payment_method',
    ];

    /**
     * ✅ A payment belongs to an order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(BarOrder::class, 'order_id');
    }
}