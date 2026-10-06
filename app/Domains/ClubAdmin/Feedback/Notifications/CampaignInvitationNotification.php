<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Notifications;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The yearly survey, to one address, naming everyone it answers for. The same
 * link for the whole family: the survey page asks « answering for » and sits
 * the guardian in the right seat.
 *
 * Also the one reminder, which never says « you have not answered »: it says
 * there is a week left.
 */
class CampaignInvitationNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $names  first names of the members this address answers for
     */
    public function __construct(public FeedbackCampaign $campaign, public array $names, public bool $isReminder = false) {}

    public function toMail(object $notifiable): MailMessage
    {
        $until = $this->campaign->closes_on->translatedFormat('j F');

        $mail = (new MailMessage)
            ->subject($this->isReminder
                ? __('A few days left to give your opinion: :title', ['title' => $this->campaign->title])
                : $this->campaign->title)
            ->greeting(__('Hello,'))
            ->line($this->campaign->intro);

        if (count($this->names) > 1) {
            $mail->line(__('One answer per person: :names.', ['names' => implode(', ', $this->names)]));
        }

        return $mail
            ->line(__('The survey is open until :date. Signed or anonymous, as you prefer.', ['date' => $until]))
            ->action(__('Give my opinion'), route('admin.user.survey'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
