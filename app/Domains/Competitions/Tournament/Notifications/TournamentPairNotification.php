<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Who a player plays with in a doubles tournament — or that they have none for now.
 */
class TournamentPairNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public Tournament $tournament,
        public ?User $partner,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->tournament->name,
            'body' => $this->partner instanceof User
                ? __('You will play with :partner.', ['partner' => $this->partner->full_name])
                : __('You have no partner for now: the committee will find you one.'),
            'url' => $this->memberEventsUrl($notifiable),
            'category' => 'tournament',
            'icon' => 'o-user-group',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__(':tournament — Your doubles partner', ['tournament' => $this->tournament->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]));

        if ($this->partner instanceof User) {
            $mail->line(__('For **:tournament**, you will play with **:partner** (:ranking).', [
                'tournament' => $this->tournament->name,
                'partner' => $this->partner->full_name,
                'ranking' => $this->partner->ranking->getLabel(),
            ]));
        } else {
            $mail->line(__('For **:tournament**, you have no partner for now: the committee will find you one and let you know.', [
                'tournament' => $this->tournament->name,
            ]));
        }

        return $mail
            ->action(__('My registrations'), $this->memberEventsUrl($notifiable))
            ->salutation(__('See you on the court!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
