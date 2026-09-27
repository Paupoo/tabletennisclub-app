<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Actions;

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Jobs\SendCommunicationJob;

/**
 * Sends a communication again to the addresses it failed to reach — and only
 * to them: whoever already received it must not receive it twice.
 */
class RetryFailedRecipients
{
    public function __invoke(Communication $communication): int
    {
        $failedIds = $communication->recipients()
            ->where('status', CommunicationRecipient::STATUS_FAILED)
            ->pluck('id');

        CommunicationRecipient::query()->whereKey($failedIds)->update([
            'status' => CommunicationRecipient::STATUS_PENDING,
            'error' => null,
        ]);

        foreach ($failedIds as $recipientId) {
            SendCommunicationJob::dispatch($recipientId);
        }

        return $failedIds->count();
    }
}
