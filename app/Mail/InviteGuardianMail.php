<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The invitation a parent receives for the accounts they answer for.
 *
 * One message per guardian rather than one per child: a parent of two members
 * receiving two near-identical emails reads it as a fault of the club, so the
 * wards are listed inside instead.
 */
class InviteGuardianMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(private Guardian $guardian, private string $link) {}

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invite-guardian',
            with: [
                'guardian' => $this->guardian,
                'link' => $this->link,
                'wards' => $this->guardian->users()->orderBy('first_name')->get(),
            ]
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Manage your family\'s membership at :app', ['app' => config('app.name')]),
            from: new Address(
                address: config('mail.from.address'),
                name: config('app.name') ?? config('mail.from.name')
            ),
            replyTo: config('mail.from.address'),
        );
    }
}
