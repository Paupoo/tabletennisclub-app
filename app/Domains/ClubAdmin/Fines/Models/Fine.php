<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Fines\Models;

use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use App\Domains\Shared\Traits\HasAuditLog;
use Database\Factories\Domains\ClubAdmin\Fines\Models\FineFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A federation fine passed on to a member. Acts as a payable: issuing it
 * generates a pending {@see Payment}, so it surfaces in the member's payments
 * hub and the treasurer view like any other due.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $issued_by
 * @property float $amount
 * @property FineReason $reason
 * @property string|null $federation_reference
 * @property string|null $description
 * @property string $pedagogical_message
 * @property-read Payment|null $payment
 * @property-read User $user
 * @property-read User|null $issuer
 *
 * @method static FineFactory factory($count = null, $state = [])
 */
class Fine extends Model implements DescribesPayment
{
    use HasAuditLog, SoftDeletes;

    /** @use HasFactory<FineFactory> */
    use HasFactory;

    protected $casts = [
        'reason' => FineReason::class,
    ];

    protected $fillable = [
        'user_id',
        'issued_by',
        'amount',
        'reason',
        'federation_reference',
        'description',
        'pedagogical_message',
    ];

    public function getPayerName(): string
    {
        return $this->user?->full_name ?? '—';
    }

    /**
     * @return array{type: string, name: string}
     */
    public function getPaymentLabel(): array
    {
        return [
            'type' => __('Fine'),
            'name' => $this->reason->label(),
        ];
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Amount in euros, stored in cents (mirrors {@see Payment}).
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): float => round($value / 100, 2),
            set: fn (int|float $value): int => (int) round($value * 100),
        );
    }
}
