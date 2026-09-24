<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Models;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une remise accordée sur une affiliation, à un instant, pour une raison.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int|null $payment_id
 * @property float $amount
 * @property string $reason
 * @property int|null $granted_by_id
 * @property Carbon $granted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscription $subscription
 * @property-read Payment|null $payment
 * @property-read User|null $grantedBy
 *
 * @mixin \Eloquent
 */
class SubscriptionDiscount extends Model
{
    use HasFactory;

    protected $casts = [
        'granted_at' => 'datetime',
    ];

    protected $fillable = [
        'subscription_id',
        'payment_id',
        'amount',
        'reason',
        'granted_by_id',
        'granted_at',
    ];

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_id');
    }

    /**
     * La communication que la remise a allégée — nulle si elle n'en a réduit
     * aucune, ou en a entamé plusieurs.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** Stocké en centimes, exposé en euros. */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (?int $value): float => round(($value ?? 0) / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }
}
