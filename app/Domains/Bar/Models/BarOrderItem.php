<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarOrderItem extends Model
{
    protected $table = 'bar_order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    /**
     * ✅ Relationship: item belongs to an order
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(BarOrder::class, 'order_id');
    }

    /**
     * ✅ Relationship: item belongs to a product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(BarProduct::class, 'product_id');
    }
}