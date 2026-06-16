<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property int $quantity
 * @property int $unit_price
 * @property int $total_price
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BarOrder $order
 * @property-read BarProduct $product
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereModifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereProductId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereQuantity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereUnitPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrderItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BarOrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
    ];

    protected $table = 'bar_order_items';

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
