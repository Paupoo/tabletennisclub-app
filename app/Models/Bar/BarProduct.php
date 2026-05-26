<?php

declare(strict_types=1);

namespace App\Models\Bar;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarProduct extends Model
{
    protected $table = 'bar_products';

    // Only fields that can be mass-assigned
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
            $model->modified_by = $userId;
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
}
