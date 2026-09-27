<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/*
| A member may be spoken for by several addresses — a teenager and both of their
| parents. Laravel would put every address of a notifiable in the same `To:`,
| which hands each separated parent the other's address. Every address gets a
| message of its own instead, whatever the notification returns from toMail().
*/

/**
 * The `To:` line of every message handed to the array transport.
 *
 * @return list<list<string>>
 */
function oneMailPerAddressRecipients(): array
{
    return collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($sent): array => collect($sent->getOriginalMessage()->getTo())
            ->map(fn ($address): string => $address->getAddress())
            ->all())
        ->values()
        ->all();
}

function oneMailPerAddressMinor(): User
{
    $member = User::factory()->create(['email' => 'teen@example.com', 'birthdate' => now()->subYears(15)]);
    $member->guardians()->attach(Guardian::factory()->create(['email' => 'mum@example.com']));
    $member->guardians()->attach(Guardian::factory()->create(['email' => 'dad@example.com']));

    return $member->fresh();
}

it('writes to each address of a member separately', function (): void {
    oneMailPerAddressMinor()->notify(new class extends Notification
    {
        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->subject('Training moved')->line('See you on Thursday.');
        }

        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    });

    expect(oneMailPerAddressRecipients())
        ->toEqualCanonicalizing([['teen@example.com'], ['mum@example.com'], ['dad@example.com']]);
});

it('writes to each address separately when the notification builds a mailable', function (): void {
    oneMailPerAddressMinor()->notify(new class extends Notification
    {
        public function toMail(object $notifiable): Mailable
        {
            return (new Mailable)->subject('Payment requested')->html('<p>Please pay.</p>');
        }

        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    });

    expect(oneMailPerAddressRecipients())
        ->toEqualCanonicalizing([['teen@example.com'], ['mum@example.com'], ['dad@example.com']]);
});

it('sends nothing when no address is on file', function (): void {
    User::factory()->create(['email' => null])->notify(new class extends Notification
    {
        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->line('Nobody reads this.');
        }

        public function via(object $notifiable): array
        {
            return ['mail'];
        }
    });

    expect(oneMailPerAddressRecipients())->toBe([]);
});
