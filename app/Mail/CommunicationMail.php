<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Jobs\SendCommunicationJob;
use App\Support\Markdown;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Collection;

/**
 * A communication, as one address receives it.
 *
 * Sent from the club — a message from a committee member's own mailbox would
 * fail the club domain's SPF and DKIM and land in spam — and answered to
 * whoever the author chose, by default themself: the questions go to whoever
 * organises. Each address is greeted as who it is.
 *
 * Queueable like every mailable, but always sent with `sendNow()`: it leaves
 * from {@see SendCommunicationJob}, which is already the throttled
 * queue job and records the address as sent only once it really is.
 */
class CommunicationMail extends Mailable implements ShouldQueue
{
    public function __construct(
        public Communication $communication,
        public CommunicationRecipient $recipient,
    ) {}

    /**
     * "Hello Marc," when the address is a member's own; "Hello, parent of Léa
     * and Tom," when it only answers for children.
     *
     * @param  Collection<int, User>  $members
     */
    public static function greeting(string $address, Collection $members): string
    {
        $own = $members->filter(fn (User $member): bool => $member->email !== null
            && mb_strtolower(trim($member->email)) === $address);

        if ($own->isNotEmpty()) {
            return __('Hello :names,', ['names' => self::names($own)]);
        }

        if ($members->isEmpty()) {
            return __('Hello,');
        }

        return __('Hello, parent of :names,', ['names' => self::names($members)]);
    }

    public function content(): Content
    {
        $author = $this->communication->author;

        return new Content(
            markdown: 'mail.communication',
            with: [
                'greeting' => self::greeting($this->recipient->email, $this->recipient->members()),
                'bodyHtml' => Markdown::safe($this->communication->body),
                'authorName' => $author?->full_name,
                'authorRole' => $author?->committee_role?->label(),
                'clubName' => Club::own()?->name ?? (string) config('app.name'),
            ],
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: (string) config('mail.from.address'),
                name: Club::own()?->name ?? (string) config('app.name'),
            ),
            replyTo: $this->communication->reply_to !== null ? [new Address($this->communication->reply_to)] : [],
            subject: $this->communication->subject,
        );
    }

    /** @param  Collection<int, User>  $members */
    private static function names(Collection $members): string
    {
        return $members->pluck('first_name')->unique()->values()->join(', ', ' ' . __('and') . ' ');
    }
}
