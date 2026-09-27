<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Communications\Actions;

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\CommunicationMail;
use Illuminate\Support\Facades\Mail;

/**
 * The last look before a message leaves for the whole club: the author
 * receives it alone, exactly as the members will, and nothing is recorded.
 */
class SendTestCommunication
{
    public function __invoke(User $author, string $subject, string $body, ?string $replyTo): void
    {
        $address = $author->contactEmail();

        if ($address === null) {
            return;
        }

        $communication = new Communication([
            'subject' => '[' . __('Test') . '] ' . $subject,
            'body' => $body,
            'reply_to' => filled($replyTo) ? $replyTo : null,
        ]);
        $communication->setRelation('author', $author);

        $recipient = new CommunicationRecipient(['email' => $address, 'user_ids' => [$author->id]]);

        Mail::to($address)->sendNow(new CommunicationMail($communication, $recipient));
    }
}
