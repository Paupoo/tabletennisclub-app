<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarProduct extends Model
{
    protected $table = 'bar_products';

    protected $fillable = [
        'name',
        'sale_price',
        'is_available',
        'category_id',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            $userId = auth()->id();
            $model->created_by = $userId;
        });

        static::updating(function ($model) {
            $model->modified_by = auth()->id();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BarCategory::class, 'category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(BarStockMovement::class, 'product_id');
    }

    /**
     * Computed stock (FIFO-ready): SUM(IN) - SUM(OUT).
     * Falls back to a physical `stock` column if present and no movements exist.
     */
    public function getStockAttribute(): int
    {
        $in  = (int) $this->stockMovements()->where('movement_type', 'in')->sum('quantity');
        $out = (int) $this->stockMovements()->where('movement_type', 'out')->sum('quantity');

        // Backward compatibility: if there are no movements, use physical column if it exists.
        if ($in === 0 && $out === 0 && array_key_exists('stock', $this->attributes)) {
            return (int) ($this->attributes['stock'] ?? 0);
        }

        return max(0, $in - $out);
    }
}
