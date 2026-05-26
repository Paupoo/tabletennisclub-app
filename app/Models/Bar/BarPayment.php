<?php

declare(strict_types=1);

namespace App\Models\Bar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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