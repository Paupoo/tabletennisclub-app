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

/**
 * Une tournée de courses du bar.
 *
 * Une seule peut être en cours à la fois : c'est ce qui dit aux autres « quelqu'un
 * y est déjà ». Close, elle ne bouge plus — une erreur se corrige par
 * l'inventaire, et la tournée reste le reflet de ce qui a été déclaré.
 *
 * @property int $id
 * @property string $status
 * @property int $shopper_id
 * @property int|null $abandoned_by
 * @property Carbon $started_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $shopper
 * @property-read Collection<int, BarRestockingLine> $lines
 */
class BarRestocking extends Model
{
    use HasAuditLog;

    public const string STATUS_ABANDONED = 'abandoned';

    public const string STATUS_CLOSED = 'closed';

    public const string STATUS_IN_PROGRESS = 'in_progress';

    protected $casts = [
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $fillable = [
        'status',
        'shopper_id',
        'abandoned_by',
        'started_at',
        'closed_at',
    ];

    protected $table = 'bar_restockings';

    /**
     * La tournée en cours, s'il y en a une.
     */
    public static function inProgress(): ?self
    {
        return self::query()->where('status', self::STATUS_IN_PROGRESS)->latest('id')->first();
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * @return HasMany<BarRestockingLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BarRestockingLine::class, 'restocking_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function shopper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shopper_id');
    }
}
