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
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $open_name_key
 * @property int $total_price
 * @property int $is_paid
 * @property string|null $paid_at
 * @property string|null $payment_method
 * @property int|null $created_by
 * @property int|null $modified_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $createdBy
 * @property-read Collection<int, BarOrderItem> $items
 * @property-read int|null $items_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereOpenNameKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereModifiedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder wherePaymentMethod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereTotalPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BarOrder whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BarOrder extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'created_by',
        'name',
        'open_name_key',
        'total_price',
        'is_paid',
        'paid_at',
        'payment_method',
        'reason',
    ];

    protected $table = 'bar_orders';

    /**
     * Le nom d'une ardoise, réduit à ce qui sert à la reconnaître.
     *
     * Minuscules, accents retirés, espaces internes réduits à un, extrémités
     * rognées. Deux barmen ne tapent jamais pareil : sans cette réduction, « Alpa A »
     * et « alpa a » ouvriraient deux ardoises, ce que le nom existe précisément pour
     * empêcher.
     *
     * La comparaison se fait ici et pas dans la base, parce que les deux ne
     * s'accordent pas : MySQL tourne en `utf8mb4_unicode_ci`, donc insensible à la
     * casse *et* aux accents, alors que SQLite — sur quoi tournent les tests — compare
     * octet par octet. Déléguer à la collation donnerait une règle verte en test et
     * fausse en production.
     *
     * Rend une chaîne vide quand il ne reste rien de significatif : « ... » ou une
     * suite d'espaces ne nomment pas une ardoise.
     */
    public static function normaliseName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        return Str::squish(Str::lower(Str::ascii($name)));
    }

    /**
     * L'ardoise ouverte qui porte ce nom, s'il y en a une.
     *
     * C'est la requête qui rend l'unicité utile plutôt que punitive : saisir le nom
     * d'une ardoise déjà ouverte ne se refuse pas, ça la rejoint.
     */
    public static function openTabNamed(?string $name): ?self
    {
        $key = self::normaliseName($name);

        if ($key === '') {
            return null;
        }

        return self::query()->where('open_name_key', $key)->first();
    }

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
