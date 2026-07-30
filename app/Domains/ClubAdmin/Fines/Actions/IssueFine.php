<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Fines\Actions;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Fines\Notifications\FineIssuedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class IssueFine
{
    /**
     * Record a federation fine for a member, generate the pending payment that
     * carries it, and send the educational notification to the member (and to
     * their guardians when the member is a minor).
     */
    public function __invoke(
        User $member,
        User $issuer,
        FineReason $reason,
        float $amount,
        string $pedagogicalMessage,
        ?string $federationReference = null,
        ?string $description = null,
    ): Fine {
        $fine = DB::transaction(function () use ($member, $issuer, $reason, $amount, $pedagogicalMessage, $federationReference, $description): Fine {
            $fine = Fine::create([
                'user_id' => $member->id,
                'issued_by' => $issuer->id,
                'amount' => $amount,
                'reason' => $reason,
                'federation_reference' => $federationReference,
                'description' => $description,
                'pedagogical_message' => $pedagogicalMessage,
            ]);

            $fine->payment()->create([
                'reference' => (new GeneratePaymentReference)(),
                'amount_due' => $amount,
                'amount_paid' => 0,
                'status' => 'pending',
            ]);

            return $fine;
        });

        $fine->load('payment', 'user.guardians');

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
