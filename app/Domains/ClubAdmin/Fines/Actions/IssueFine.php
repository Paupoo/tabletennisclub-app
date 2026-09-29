<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Fines\Actions;

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Fines\Services\FineCreditor;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class IssueFine
{
    /**
     * Record a provincial committee fine for a member and send them (and their
     * guardians when the member is a minor) what they need to pay it directly.
     *
     * No payment of the club's is created: the club collects nothing, and a
     * claim no transfer to the club would ever settle would stay open forever.
     *
     * @throws DomainException when the committee's account is not configured yet
     */
    public function __invoke(
        User $member,
        User $issuer,
        FineReason $reason,
        float $amount,
        string $pedagogicalMessage,
        Carbon $eventDate,
        string $eventLabel,
        Carbon $paymentDeadline,
        ?int $provincialCode = null,
    ): Fine {
        if (! app(FineCreditor::class)->isConfigured()) {
            throw new DomainException('The provincial committee account must be configured before issuing a fine.');
        }

        $fine = Fine::create([
            'user_id' => $member->id,
            'issued_by' => $issuer->id,
            'amount' => $amount,
            'reason' => $reason,
            'provincial_code' => $provincialCode,
            'event_date' => $eventDate,
            'event_label' => $eventLabel,
            'payment_deadline' => $paymentDeadline,
            'pedagogical_message' => $pedagogicalMessage,
        ]);

        $fine->load('user.guardians');

        $fine->user->notify(new FineIssuedNotification($fine));

        if ($fine->user->isMinor()) {
            foreach ($fine->user->guardians as $guardian) {
                if (filled($guardian->email)) {
                    Notification::route('mail', $guardian->email)->notify(new FineIssuedNotification($fine));
                }
            }
        }

        return $fine;
    }
}
