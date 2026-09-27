<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Actions;

use App\Domains\ClubAdmin\Communications\Data\AudienceCriteria;
use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\ClubAdmin\Communications\Services\AudienceBuilder;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Jobs\SendCommunicationJob;
use Illuminate\Support\Facades\DB;

/**
 * Sends a message written in the application to an audience.
 *
 * The audience is frozen when the author clicks: every address is recorded
 * before the first message leaves, so a member who joins during the few
 * minutes the throttled sending takes is not added halfway, and a retry knows
 * exactly who is still owed the message.
 */
class SendCommunication
{
    public function __construct(private readonly AudienceBuilder $audiences) {}

    public function __invoke(User $author, AudienceCriteria $criteria, string $subject, string $body, ?string $replyTo): Communication
    {
        $audience = $this->audiences->build($criteria);

        $communication = DB::transaction(function () use ($author, $criteria, $subject, $body, $replyTo, $audience): Communication {
            $communication = Communication::create([
                'author_id' => $author->id,
                'subject' => $subject,
                'body' => $body,
                'reply_to' => filled($replyTo) ? $replyTo : null,
                'criteria' => $criteria->toArray(),
                'member_count' => $audience->members->count(),
                'recipient_count' => count($audience->recipients),
                'sent_at' => now(),
            ]);

            foreach ($audience->recipients as $address => $members) {
                $communication->recipients()->create([
                    'email' => $address,
                    'user_ids' => array_map(fn (User $member): int => $member->id, $members),
                    'status' => CommunicationRecipient::STATUS_PENDING,
                ]);
            }

            return $communication;
        });

        foreach ($communication->recipients()->pluck('id') as $recipientId) {
            SendCommunicationJob::dispatch($recipientId);
        }

        return $communication;
    }
}
