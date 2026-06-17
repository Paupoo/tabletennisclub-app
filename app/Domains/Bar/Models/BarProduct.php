<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property int $sale_price
 * @property int $is_available
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BarCategory $category
 * @property-read User|null $createdBy
 * @property-read int $stock
 * @property-read User|null $modifiedBy
 * @property-read Collection<int, BarStockMovement> $stockMovements
 * @property-read int|null $stock_movements_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereIsAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereModifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereSalePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BarProduct extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'name',
        'sale_price',
        'is_available',
        'category_id',
    ];

    protected $table = 'bar_products';

    public function category(): BelongsTo
    {
        return $this->belongsTo(BarCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Computed stock (FIFO-ready): SUM(IN) - SUM(OUT).
     * Falls back to a physical `stock` column if present and no movements exist.
     */
    public function getStockAttribute(): int
    {
        $in = (int) $this->stockMovements()->where('movement_type', 'IN')->sum('quantity');
        $out = (int) $this->stockMovements()->where('movement_type', 'OUT')->sum('quantity');

        // Backward compatibility: if there are no movements, use physical column if it exists.
        if ($in === 0 && $out === 0 && array_key_exists('stock', $this->attributes)) {
            return (int) ($this->attributes['stock'] ?? 0);
        }

        return max(0, $in - $out);
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(BarStockMovement::class, 'product_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $userId = auth()->id();
            $model->created_by = $userId;
        });

        static::updating(function (self $model): void {
            $model->modified_by = auth()->id();
        });
    }
}
