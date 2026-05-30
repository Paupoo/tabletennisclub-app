<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarStockMovement extends Model
{
    protected $table = 'bar_stock_movements';

    protected $fillable = [
        'product_id',
        'quantity',
        'movement_type', // IN / OUT
        'reason',
        'created_by',
        'modified_by',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(BarProduct::class, 'product_id');
    }
}