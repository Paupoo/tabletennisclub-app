<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Tournament;

use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TournamentRegistration extends Pivot
{
    public $incrementing = true;

    protected $casts = [
        'has_paid' => 'boolean',
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
        'payment_id',
    ];

    protected $table = 'tournament_user';

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
