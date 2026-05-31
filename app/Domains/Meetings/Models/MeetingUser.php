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
 */
class MeetingUser extends Pivot implements DescribesPayment
{
    public $incrementing = true;

    protected $casts = [
        'status' => MeetingUserStatusEnum::class,
        'invitation_sent_at' => 'datetime',
        'response_at' => 'datetime',
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
            'type' => __('Meeting'),
            'name' => $this->meeting?->title ?? '—',
        ];
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
