<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Builder;
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
 * @property int|null $low_stock_threshold
 * @property int|null $max_stock
 * @property int $pack_size
 * @property string|null $pack_label
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BarCategory $category
 * @property-read User|null $createdBy
 * @property-read int $stock
 * @property-read int $effective_low_stock_threshold
 * @property-read bool $is_low_stock
 * @property-read int|null $stock_movements_sum_in
 * @property-read int|null $stock_movements_sum_out
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarProduct whereLowStockThreshold($value)
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

    /**
     * Le seuil d'alerte du bar, quand un produit n'en déclare pas.
     *
     * Il vivait en dur dans deux vues (`$realStock <= 3`), qui pouvaient donc
     * diverger sans que rien ne le dise. Une seule écriture, lue par l'écran de
     * vente, par le tableau de stock et par son tri de criticité.
     */
    public const int LOW_STOCK_THRESHOLD = 3;

    protected $attributes = [
        'pack_size' => 1,
    ];

    protected $casts = [
        'max_stock' => 'integer',
        'pack_size' => 'integer',
    ];

    protected $fillable = [
        'name',
        'sale_price',
        'is_available',
        'low_stock_threshold',
        'max_stock',
        'pack_size',
        'pack_label',
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
     * Le seuil qui s'applique vraiment à ce produit : le sien, ou celui du bar.
     *
     * Un seuil absent veut dire « prends le défaut », pas « pas d'alerte » : un
     * produit créé sans y penser reste surveillé. Zéro est donc une valeur
     * significative et distincte de null — on le respecte.
     */
    public function getEffectiveLowStockThresholdAttribute(): int
    {
        return $this->low_stock_threshold ?? self::LOW_STOCK_THRESHOLD;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->stock <= $this->effective_low_stock_threshold;
    }

    /**
     * Computed stock (FIFO-ready): SUM(IN) - SUM(OUT).
     *
     * Deux requêtes par lecture quand le produit arrive seul — mesuré : 20 requêtes
     * pour 9 produits sur l'écran de vente, donc une centaine sur un vrai
     * catalogue, à chaque affichage. Une liste doit donc être chargée par
     * {@see self::scopeWithStock()}, qui agrège les deux sommes en une requête ;
     * cet accesseur s'en sert dès qu'elles sont présentes.
     */
    public function getStockAttribute(): int
    {
        if (array_key_exists('stock_in', $this->attributes)) {
            return max(0, (int) $this->attributes['stock_in'] - (int) ($this->attributes['stock_out'] ?? 0));
        }

        $in = (int) $this->stockMovements()->where('movement_type', BarStockMovement::TYPE_IN)->sum('quantity');
        $out = (int) $this->stockMovements()->where('movement_type', BarStockMovement::TYPE_OUT)->sum('quantity');

        return max(0, $in - $out);
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    /**
     * Charge le stock de chaque produit sans une requête par produit.
     *
     * `withSum` produit une sous-requête corrélée par colonne, donc deux pour
     * toute la liste au lieu de deux par ligne. Les alias `stock_in` / `stock_out`
     * sont ceux que {@see self::getStockAttribute()} cherche.
     *
     * @param  Builder<self>  $query
     */
    public function scopeWithStock(Builder $query): void
    {
        $query->withSum(
            ['stockMovements as stock_in' => fn ($q) => $q->where('movement_type', BarStockMovement::TYPE_IN)],
            'quantity'
        )->withSum(
            ['stockMovements as stock_out' => fn ($q) => $q->where('movement_type', BarStockMovement::TYPE_OUT)],
            'quantity'
        );
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(BarStockMovement::class, 'product_id');
    }

    #[\Override]
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
