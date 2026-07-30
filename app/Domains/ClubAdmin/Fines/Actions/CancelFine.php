<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Fines\Actions;

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineCancelledNotification;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CancelFine
{
    /**
     * Reverse a fine issued by mistake: cancel its pending payment, soft-delete
     * the fine, and reassure the member (and their guardians when the member is
     * a minor) that they no longer owe anything.
     *
     * A fine whose payment has already been collected cannot be cancelled here —
     * it would leave the money unaccounted for; use the refund flow instead.
     *
     * @throws DomainException when the fine's payment has already been paid
     */
    public function __invoke(Fine $fine): void
    {
        $fine->loadMissing('payment', 'user.guardians');

        if ($fine->payment?->status === 'paid') {
            throw new DomainException('Cannot cancel a fine that has already been paid.');
        }

        DB::transaction(function () use ($fine): void {
            $fine->payment?->update(['status' => 'cancelled']);
            $fine->delete();
        });

        $fine->user->notify(new FineCancelledNotification($fine));

        if ($fine->user->isMinor()) {
            foreach ($fine->user->guardians as $guardian) {
                if (filled($guardian->email)) {
                    Notification::route('mail', $guardian->email)->notify(new FineCancelledNotification($fine));
                }
            }
        }
    }
}
