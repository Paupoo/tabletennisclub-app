<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarStockMovement extends Model
{
    public const TYPE_IN = 'IN';

    public const TYPE_OUT = 'OUT';

    public const TYPES = [
        self::TYPE_IN,
        self::TYPE_OUT,
    ];

    protected $casts = [
        'product_id' => 'integer',
        'quantity' => 'integer',
        'remaining_quantity' => 'integer',
        'movement_type' => 'string',
        'reason' => 'string',
        'created_by' => 'integer',
        'modified_by' => 'integer',
        'source_movement_id' => 'integer',
        'order_id' => 'integer',
        'order_item_id' => 'integer',
    ];

    protected $fillable = [
        'product_id',
        'quantity',
        'remaining_quantity',
        'movement_type',
        'reason',
        'created_by',
        'modified_by',
        'source_movement_id',
        'order_id',
        'order_item_id',
    ];

    protected $table = 'bar_stock_movements';

    public function getSignedQuantityAttribute(): int
    {
        return $this->isIn() ? $this->quantity : -$this->quantity;
    }

    public function isIn(): bool
    {
        return $this->movement_type === self::TYPE_IN;
    }

    public function isOut(): bool
    {
        return $this->movement_type === self::TYPE_OUT;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(BarOrder::class, 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(BarOrderItem::class, 'order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(BarProduct::class, 'product_id');
    }

    public function sourceMovement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_movement_id');
    }
}
