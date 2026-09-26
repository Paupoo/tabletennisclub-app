<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une ligne de la liste figée par une tournée.
 *
 * `section` dit d'où vient la ligne : `to_buy` (au min ou dessous), `if_room`
 * (entre min et max) ou `extra` (ajoutée à la clôture, « ils avaient des Chimay
 * en promo »). Stock, conditionnement et quantité proposée sont ceux du départ.
 *
 * @property int $id
 * @property int $restocking_id
 * @property int $product_id
 * @property string $section
 * @property int $stock_at_start
 * @property int $pack_size
 * @property string|null $pack_label
 * @property int $proposed_packs
 * @property bool $in_cart
 * @property int|null $bought_packs
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BarProduct $product
 * @property-read BarRestocking $restocking
 */
class BarRestockingLine extends Model
{
    public const string SECTION_EXTRA = 'extra';

    public const string SECTION_IF_ROOM = 'if_room';

    public const string SECTION_TO_BUY = 'to_buy';

    protected $casts = [
        'stock_at_start' => 'integer',
        'pack_size' => 'integer',
        'proposed_packs' => 'integer',
        'in_cart' => 'boolean',
        'bought_packs' => 'integer',
    ];

    protected $fillable = [
        'restocking_id',
        'product_id',
        'section',
        'stock_at_start',
        'pack_size',
        'pack_label',
        'proposed_packs',
        'in_cart',
        'bought_packs',
    ];

    protected $table = 'bar_restocking_lines';

    /**
     * @return BelongsTo<BarProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(BarProduct::class, 'product_id');
    }

    /**
     * @return BelongsTo<BarRestocking, $this>
     */
    public function restocking(): BelongsTo
    {
        return $this->belongsTo(BarRestocking::class, 'restocking_id');
    }
}
