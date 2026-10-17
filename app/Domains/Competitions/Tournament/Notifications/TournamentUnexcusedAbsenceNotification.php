<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A registered player who did not come and did not warn: the entry stays due.
 *
 * Written to explain rather than to scold — the place was held, the day was
 * organised around it — so that next time the player warns ahead.
 */
class TournamentUnexcusedAbsenceNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public Tournament $tournament,
        public Payment $payment,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Your absence from :name', ['name' => $this->tournament->name]),
            'body' => __('Your entry remains due.'),
            'url' => $this->memberEventsUrl($notifiable),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $club = Club::ourClub()->first();

        $message = (new MailMessage)
            ->subject(__('Your absence from :name', ['name' => $this->tournament->name]))
            ->greeting(__('Hi') . ' ' . $notifiable->first_name . ' !')
            ->line(__('We did not see you at :name on :date, and we had not been told you could not come.', [
                'name' => '**' . $this->tournament->name . '**',
                'date' => $this->tournament->start_date?->format('d/m/Y') ?? '',
            ]))
            ->line($this->tournament->match_type === 'double'
                ? __('Your place was held: the pairs, the pools and the tables were planned with you, and your partner had to be paired again on the spot.')
                : __('Your place was held: the pools and the tables were planned with you.'))
            ->line(__('Your entry of **:amount €** therefore remains due.', [
                'amount' => number_format($this->payment->amount_due, 2, ',', ' '),
            ]))
            ->line('---')
            ->line(__('Structured reference: :ref', ['ref' => $this->payment->reference]));

        if ($club !== null) {
            $message->line(__('payment.iban_bic_line', ['iban' => $club->bank_account_formatted, 'bic' => $club->bic]));
        }

        return $message
            ->line('---')
            ->line(__('Next time, a word to the organisers before the day is enough: an absence announced in time frees the place for someone else, and costs nothing.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
