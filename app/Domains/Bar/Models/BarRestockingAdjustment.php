<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un ajustement du réassort automatique : un produit, son min et son max avant et après.
 *
 * @property int $id
 * @property int $product_id
 * @property int|null $old_min
 * @property int $new_min
 * @property int|null $old_max
 * @property int $new_max
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BarProduct $product
 */
class BarRestockingAdjustment extends Model
{
    protected $casts = [
        'product_id' => 'integer',
        'old_min' => 'integer',
        'new_min' => 'integer',
        'old_max' => 'integer',
        'new_max' => 'integer',
    ];

    protected $fillable = [
        'product_id',
        'old_min',
        'new_min',
        'old_max',
        'new_max',
    ];

    protected $table = 'bar_restocking_adjustments';

    /**
     * @return BelongsTo<BarProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(BarProduct::class, 'product_id');
    }
}
