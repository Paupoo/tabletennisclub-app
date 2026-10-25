<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use App\Mail\TournamentRegistrationMail;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What a player hears when they register: one message, whether the tournament
 * is free or paid. A paid registration carries its payment request — once
 * separate, it told the player "not final until paid" right after this one had
 * said "confirmed". Waiting-list entries keep their own wording.
 */
class TournamentRegistrationConfirmedNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public Tournament $tournament,
        public bool $isWaitlisted = false,
        public int $waitlistPosition = 0,
        public ?Payment $payment = null,
        public ?Carbon $deadline = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->isWaitlisted
                ? __('On the waiting list: :name', ['name' => $this->tournament->name])
                : __('Registration recorded: :name', ['name' => $this->tournament->name]),
            'body' => match (true) {
                $this->isWaitlisted => __('Waiting list #:position', ['position' => $this->waitlistPosition]),
                $this->payment !== null && $this->deadline !== null => __('To pay: :amount € before :date', [
                    'amount' => number_format($this->payment->amount_due, 2, ',', ' '),
                    'date' => $this->deadline->format('d/m/Y'),
                ]),
                default => __('See the tournament details'),
            },
            'url' => $this->memberEventsUrl($notifiable),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage|TournamentRegistrationMail
    {
        if (! $this->isWaitlisted && $notifiable instanceof User) {
            // Addressed by PerRecipientMailChannel: one message per contact
            // address, so a minor's guardians are reached rather than a null login.
            return new TournamentRegistrationMail($this->tournament, $notifiable, $this->payment, $this->deadline);
        }

        $tournament = $this->tournament;
        $tournament->loadMissing('rooms');

        $matchType = $tournament->match_type === 'double' ? __('Doubles') : __('Singles');
        $sets = __(':n winning sets (Best of :total)', [
            'n' => $tournament->sets_to_win,
            'total' => ($tournament->sets_to_win * 2) - 1,
        ]);
        $handicap = $tournament->has_handicap_points ? __('Yes') : __('No');
        $location = $tournament->rooms->pluck('name')->join(', ') ?: '—';
        $dateStr = $tournament->start_date?->format('d/m/Y') ?? '—';
        $timeStr = $tournament->start_time ?? '—';

        $mail = (new MailMessage)
            ->subject(__(':tournament — Waitlist confirmation', ['tournament' => $tournament->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('You have been added to the waiting list for **:tournament**.', ['tournament' => $tournament->name]))
            ->line(__('Your current position: **#:position**', ['position' => $this->waitlistPosition]))
            ->line(__('This position may move up as other players withdraw.'))
            ->line(__('If a spot opens up, you will receive an email to confirm your participation. You will have 48 hours to respond — after that, the spot will be offered to the next person on the list.'))
            ->line('---')
            ->line(__('**Date:** :date at :time', ['date' => $dateStr, 'time' => $timeStr]))
            ->line(__('**Location:** :rooms', ['rooms' => $location]))
            ->line(__('**Format:** :type — :sets', ['type' => $matchType, 'sets' => $sets]))
            ->line(__('**Handicap points:** :handicap', ['handicap' => $handicap]));

        if ($tournament->isPaid()) {
            $fee = $notifiable instanceof User ? $tournament->priceFor($notifiable) : $tournament->price;
            $mail->line(__('**Entry fee:** :price €', ['price' => number_format($fee, 2)]));
        }

        if ($tournament->registration_deadline) {
            $mail->line(__('**Registration deadline:** :date', [
                'date' => $tournament->registration_deadline->format('d/m/Y'),
            ]));
        }

        return $mail->salutation(__('See you on the court!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
