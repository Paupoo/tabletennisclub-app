<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarProduct extends Model
{
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
        static::creating(function ($model) {
            $userId = auth()->id();
            $model->created_by = $userId;
        });

        static::updating(function ($model) {
            $model->modified_by = auth()->id();
        });
    }
}
