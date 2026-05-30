<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Notifications;

use App\Domains\Meetings\Models\Meeting;
use App\Services\IcsGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class MeetingPostponedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public string $message = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Réunion reportée: :title', ['title' => $this->meeting->title]),
            'body' => __('La réunion a été reportée au :date', ['date' => $this->meeting->postponed_to?->translatedFormat('d M Y') ?? __('TBD')]),
            'url' => route('admin.meetings.show', $this->meeting),
            'category' => 'meeting',
            'icon' => 'o-calendar-days',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Postponed: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->first_name]))
            ->line(__('The meeting **:title** has been **postponed**.', ['title' => $this->meeting->title]));

        if ($this->meeting->postponed_to) {
            $mail->line(__('New proposed date: **:date**', [
                'date' => $this->meeting->postponed_to->translatedFormat('d M Y · H\hi'),
            ]));
        }

        if (filled($this->message)) {
            $mail->line('---')->line($this->message);
        }

        // ICS mis à jour avec la nouvelle date proposée (si connue)
        $meeting = $this->meeting;
        $meeting->loadMissing('agendaItems');
        $ics = app(IcsGenerator::class)->forMeeting($meeting);

        return $mail
            ->attachData($ics, 'meeting-update.ics', ['mime' => 'text/calendar'])
            ->salutation(__('Regards,'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
