<?php

declare(strict_types=1);

namespace App\Domains\Bar\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\BarInventoryCause;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un inventaire du bar : le seul geste qui corrige le stock.
 *
 * Un seul peut être en cours à la fois, et tous ceux qui gèrent le stock y
 * comptent ensemble. Validé, il ne bouge plus — une erreur se corrige par un
 * nouvel inventaire. Annulé, il n'a rien écrit.
 *
 * @property int $id
 * @property string $status
 * @property int $opened_by
 * @property int|null $closed_by
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $opener
 * @property-read User|null $closer
 * @property-read Collection<int, BarInventoryLine> $lines
 */
class BarInventory extends Model
{
    use HasAuditLog;

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_IN_PROGRESS = 'in_progress';

    public const string STATUS_VALIDATED = 'validated';

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $fillable = [
        'status',
        'opened_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'comment',
    ];

    protected $table = 'bar_inventories';

    /**
     * L'inventaire en cours, s'il y en a un.
     */
    public static function inProgress(): ?self
    {
        return self::query()->where('status', self::STATUS_IN_PROGRESS)->latest('id')->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    /**
     * @return HasMany<BarInventoryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BarInventoryLine::class, 'inventory_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * Manques, surplus et valeur au prix de vente, sans ce qui vient d'être ajouté au bar.
     *
     * Un produit qui arrive au bar n'est ni une perte ni un surplus : il ne pèse
     * pas sur le total que lit le trésorier.
     *
     * @return array{missing: int, surplus: int, value: int}
     */
    public function totals(): array
    {
        $totals = ['missing' => 0, 'surplus' => 0, 'value' => 0];

        foreach ($this->lines as $line) {
            if ($line->gap === 0 || $line->cause === BarInventoryCause::AddedToBar) {
                continue;
            }

            $totals[$line->gap < 0 ? 'missing' : 'surplus'] += abs($line->gap);
            $totals['value'] += $line->gap * (int) $line->unit_price;
        }

        return $totals;
    }
}
