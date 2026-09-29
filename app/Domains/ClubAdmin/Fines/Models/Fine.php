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
use Illuminate\Support\Carbon;

/**
 * A provincial committee fine passed on to a member, who pays the committee
 * directly: the club collects nothing and does not know whether it was paid.
 *
 * Fines issued before that rule carried a {@see Payment} of the club's, since
 * cancelled; the relation stays so those payments still name what they were.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $issued_by
 * @property float $amount
 * @property FineReason $reason
 * @property int|null $provincial_code
 * @property Carbon|null $event_date
 * @property string|null $event_label
 * @property Carbon|null $payment_deadline
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
        'provincial_code' => 'integer',
        'event_date' => 'date',
        'payment_deadline' => 'date',
    ];

    protected $fillable = [
        'user_id',
        'issued_by',
        'amount',
        'reason',
        'provincial_code',
        'event_date',
        'event_label',
        'payment_deadline',
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

    /**
     * Whether the member can still pay before losing their qualification. The
     * deadline day itself still counts: the committee reads it as "on our
     * account by then".
     */
    public function isPayable(): bool
    {
        return $this->payment_deadline !== null && ! $this->payment_deadline->isBefore(today());
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    /**
     * What the member writes on the transfer. The committee hands out no
     * reference, so its treasurer matches a payment by who, when and why —
     * cut to the 140 characters a SEPA transfer carries.
     */
    public function transferCommunication(): string
    {
        $parts = array_filter([
            trim(mb_strtoupper((string) $this->user?->last_name) . ' ' . $this->user?->first_name),
            $this->event_date?->format('d/m/Y'),
            $this->reason->label(),
        ]);

        return mb_substr(implode(' – ', $parts), 0, 140);
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
