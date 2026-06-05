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

class MeetingCancelledNotification extends Notification implements ShouldQueue
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
            'title' => __('Réunion annulée: :title', ['title' => $this->meeting->title]),
            'body' => __('La réunion prévue pour :date a été annulée.', ['date' => $this->meeting->scheduled_at?->translatedFormat('d M Y') ?? __('TBD')]),
            'url' => route('admin.meetings.show', $this->meeting),
            'category' => 'meeting',
            'icon' => 'o-calendar-days',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Cancelled: :title', ['title' => $this->meeting->title]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->first_name]))
            ->line(__('The meeting **:title** scheduled for :date has been **cancelled**.', [
                'title' => $this->meeting->title,
                'date' => $this->meeting->scheduled_at?->translatedFormat('d M Y') ?? __('TBD'),
            ]));

        if (filled($this->message)) {
            $mail->line('---')->line($this->message);
        }

        // ICS avec METHOD:CANCEL pour supprimer l'événement du calendrier
        $meeting = $this->meeting;
        $meeting->loadMissing('agendaItems');
        $ics = app(IcsGenerator::class)->forMeeting($meeting, cancel: true);

        return $mail
            ->attachData($ics, 'meeting-cancel.ics', ['mime' => 'text/calendar'])
            ->salutation(__('Regards,'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
