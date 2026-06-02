<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarOrder extends Model
{
    protected $fillable = [
        'created_by',
        'total_price',
        'is_paid',
        'paid_at',
        'payment_method',
        'reason',
    ];

    protected $table = 'bar_orders';

    /**
     * ✅ Optional: who created the order
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * ✅ An order has many items
     */
    public function items(): HasMany
    {
        return $this->hasMany(BarOrderItem::class, 'order_id');
    }
}
