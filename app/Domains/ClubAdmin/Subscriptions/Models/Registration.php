<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Models;

use App\Contracts\PayableInterface;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use Database\Factories\Domains\ClubAdmin\Subscriptions\Models\RegistrationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $event_post_id
 * @property int $user_id
 * @property float $amount_due
 * @property int $amount_paid
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Payment> $payments
 * @property-read int|null $payments_count
 *
 * @method static \Database\Factories\Domains\ClubAdmin\Subscriptions\Models\RegistrationFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereAmountDue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereEventPostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Registration whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Registration extends Model implements PayableInterface
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    public function getAmountDue(): int|float
    {
        return $this->getAttribute('amount_due');
    }

    // ==================== Relations ====================

    /**
     * Tous les paiements associés à cette registration
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    // ==================== Mutators ====================
    protected function amountDue(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) ($value * 100),
        );
    }
}
