<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Models;

use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $tournament_id
 * @property bool $has_paid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $registration_status
 * @property int|null $waitlist_position
 * @property Carbon|null $confirmation_deadline
 * @property Carbon|null $payment_deadline
 * @property int|null $payment_id
 * @property bool $qr_confirmed
 * @property-read Payment|null $payment
 * @property-read Tournament|null $tournament
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereConfirmationDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereHasPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration wherePaymentDeadline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereQrConfirmed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereRegistrationStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereTournamentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TournamentRegistration whereWaitlistPosition($value)
 *
 * @mixin \Eloquent
 */
class TournamentRegistration extends Pivot implements DescribesPayment
{
    use HasAuditLog;

    public $incrementing = true;

    protected $casts = [
        'has_paid' => 'boolean',
        'qr_confirmed' => 'boolean',
        'confirmation_deadline' => 'datetime',
        'payment_deadline' => 'datetime',
        'waitlist_position' => 'integer',
    ];

    protected $fillable = [
        'user_id',
        'tournament_id',
        'registration_status',
        'waitlist_position',
        'confirmation_deadline',
        'payment_deadline',
        'has_paid',
        'qr_confirmed',
        'payment_id',
    ];

    protected $table = 'tournament_user';

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
            'type' => __('Tournament'),
            'name' => $this->tournament?->name ?? '—',
        ];
    }

    public function hasPaymentPending(): bool
    {
        return $this->payment_deadline !== null
            && $this->payment_deadline->isFuture()
            && ! $this->has_paid;
    }

    public function isActive(): bool
    {
        return in_array($this->registration_status, ['registered', 'confirmed', 'spot_offered']);
    }

    public function isOnWaitlist(): bool
    {
        return $this->registration_status === 'waiting';
    }

    public function isSpotOffered(): bool
    {
        return $this->registration_status === 'spot_offered';
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
