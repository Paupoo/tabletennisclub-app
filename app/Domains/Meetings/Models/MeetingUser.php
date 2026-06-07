<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Models;

use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $meeting_id
 * @property int $user_id
 * @property MeetingUserStatusEnum $status
 * @property Carbon|null $invitation_sent_at
 * @property Carbon|null $response_at
 * @property bool|null $meal_reserved
 * @property Carbon|null $meal_responded_at
 * @property-read Payment|null $payment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereInvitationSentAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereMealReserved($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereMealRespondedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereMeetingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereResponseAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|MeetingUser whereUserId($value)
 *
 * @mixin \Eloquent
 */
class MeetingUser extends Pivot implements DescribesPayment
{
    public $incrementing = true;

    protected $casts = [
        'status' => MeetingUserStatusEnum::class,
        'invitation_sent_at' => 'datetime',
        'response_at' => 'datetime',
        'meal_reserved' => 'boolean',
        'meal_responded_at' => 'datetime',
    ];

    protected $table = 'meeting_user';

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
            'type' => __('Meeting'),
            'name' => $this->meeting?->title ?? '—',
        ];
    }

    public function hasReservedMeal(): bool
    {
        return $this->meal_reserved === true;
    }

    /**
     * A meal payment is locked once it has been (partially) paid: the meal can
     * no longer be cancelled online, only adjusted by an organizer.
     */
    public function mealPaymentLocked(): bool
    {
        $payment = $this->payment;

        return $payment !== null && ($payment->amount_paid > 0 || $payment->status !== 'pending');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
