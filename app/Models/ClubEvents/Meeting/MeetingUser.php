<?php

declare(strict_types=1);

namespace App\Models\ClubEvents\Meeting;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
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
class MeetingUser extends Pivot
{
    public $incrementing = true;

    protected $casts = [
        'status' => MeetingUserStatusEnum::class,
        'invitation_sent_at' => 'datetime',
        'response_at' => 'datetime',
    ];

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'payable');
    }
}
