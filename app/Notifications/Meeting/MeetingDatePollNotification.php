<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Models\ClubEvents\Meeting\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class MeetingDatePollNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Meeting $meeting) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Sondage de dates: :title', ['title' => $this->meeting->title]),
            'body' => __('Merci de voter pour votre disponibilité'),
            'url' => route('admin.meetings.show', $this->meeting),
            'category' => 'meeting',
            'icon' => 'o-calendar-days',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting;

        $mail = (new MailMessage)
            ->subject(__('Date poll: :title', ['title' => $meeting->title]))
            ->greeting(__('Hi :name!', ['name' => $notifiable->first_name]))
            ->line(__('We need your availability to schedule the meeting **:title**.', ['title' => $meeting->title]));

        if ($meeting->description) {
            $mail->line($meeting->description);
        }

        foreach ($meeting->dateProposals as $i => $proposal) {
            $mail->line(($i + 1) . '. ' . $proposal->proposed_at->translatedFormat('l d M Y · H\hi'));
        }

        $pollUrl = URL::signedRoute(
            'meetings.poll.vote',
            ['meeting' => $meeting->id, 'user' => $notifiable->id],
            now()->addDays(14),
        );

        return $mail
            ->action(__('Vote on your availability'), $pollUrl)
            ->line(__('Your vote helps us pick the best date for everyone.'))
            ->salutation(__('Thank you!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
