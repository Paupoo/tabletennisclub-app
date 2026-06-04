<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $total_price
 * @property int $is_paid
 * @property string|null $paid_at
 * @property string|null $payment_method
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $createdBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Domains\Bar\Models\BarOrderItem> $items
 * @property-read int|null $items_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereModifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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
