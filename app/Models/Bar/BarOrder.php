<?php

declare(strict_types=1);

namespace App\Models\Bar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class BarOrder extends Model
{
    protected $table = 'bar_orders';

    protected $fillable = [
        'created_by',
        'total_price',
        'is_paid',
        'paid_at',
        'payment_method',
    ];

    /**
     * ✅ An order has many items
     */
    public function items(): HasMany
    {
        return $this->hasMany(BarOrderItem::class, 'order_id');
    }

    /**
     * ✅ Optional: who created the order
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}