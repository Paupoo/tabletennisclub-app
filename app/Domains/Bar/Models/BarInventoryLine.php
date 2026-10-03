<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\BarInventoryCause;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ce qui a été compté d'un produit pendant un inventaire.
 *
 * `expected` est le stock au moment où `counted` a été saisi : l'écart se lit
 * entre les deux, quoi que le bar vende ensuite.
 *
 * @property int $id
 * @property int $inventory_id
 * @property int $product_id
 * @property int $expected
 * @property int $counted
 * @property int|null $counted_by
 * @property Carbon $counted_at
 * @property BarInventoryCause|null $cause
 * @property string|null $note
 * @property int|null $unit_price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $gap
 * @property-read BarInventory $inventory
 * @property-read BarProduct $product
 * @property-read User|null $counter
 */
class BarInventoryLine extends Model
{
    protected $casts = [
        'expected' => 'integer',
        'counted' => 'integer',
        'counted_at' => 'datetime',
        'cause' => BarInventoryCause::class,
        'unit_price' => 'integer',
    ];

    protected $fillable = [
        'inventory_id',
        'product_id',
        'expected',
        'counted',
        'counted_by',
        'counted_at',
        'cause',
        'note',
        'unit_price',
    ];

    protected $table = 'bar_inventory_lines';

    /**
     * @return BelongsTo<User, $this>
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function getGapAttribute(): int
    {
        return $this->counted - $this->expected;
    }

    /**
     * @return BelongsTo<BarInventory, $this>
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(BarInventory::class, 'inventory_id');
    }

    /**
     * @return BelongsTo<BarProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(BarProduct::class, 'product_id');
    }
}
