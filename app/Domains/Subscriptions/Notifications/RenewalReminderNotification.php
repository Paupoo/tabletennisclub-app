<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Notifications;

use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The reminder written to a member of last season who has not registered for
 * the running one.
 *
 * A notification rather than a mailable for its addressing: the mail channel
 * asks the member for `routeNotificationForMail()`, which writes to a child's
 * parents as well as to the child — the same envelope as every other message
 * the club sends a member. Mail only: there is nothing for the bell to keep
 * once the member has registered.
 *
 * Not queued on its own. It is sent from inside a throttled job, which is what
 * spaces the mailing out; queueing it again would pace the dispatching instead
 * of the sending.
 */
class RenewalReminderNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(public readonly Season $season) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('We have not seen you yet for the :season season', ['season' => $this->season->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('Last season you were one of us, and your affiliation for :season has not been renewed yet.', ['season' => $this->season->name]))
            ->action(__('Renew the affiliation for :season', ['season' => $this->season->name]), $this->memberTrainingsUrl($notifiable))
            ->line(__('Not coming back this season? Simply reply to this email to let us know.'))
            ->salutation(__('See you soon at the club!'));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
