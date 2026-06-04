<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $dashboardUrl,
    ) {}

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.member-welcome',
            with: [
                'user' => $this->user,
                'dashboardUrl' => $this->dashboardUrl,
            ]
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                address: config('mail.from.address'),
                name: config('app.name') ?? config('mail.from.name')
            ),
            subject: __('Welcome to :club!', ['club' => config('app.name')]),
        );
    }
}
