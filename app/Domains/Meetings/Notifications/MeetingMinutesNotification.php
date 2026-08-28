<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Notifications;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Traits\LinksToMemberSpace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class MeetingMinutesNotification extends Notification implements ShouldQueue
{
    use LinksToMemberSpace, Queueable, SerializesModels;

    public function __construct(public Meeting $meeting) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Minutes: :title', ['title' => $this->meeting->title]),
            'body' => __('The minutes of the :date meeting are available', ['date' => $this->meeting->scheduled_at?->translatedFormat('d M Y') ?? __('TBD')]),
            'url' => $this->meetingUrl($notifiable, $this->meeting),
            'category' => 'meeting',
            'icon' => 'o-calendar-days',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting;
        $minutes = $meeting->minutes;

        $mail = (new MailMessage)
            ->subject(__('Minutes: :title', ['title' => $meeting->title]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->first_name]))
            ->line(__('The minutes for **:title** (held on :date) are now available.', [
                'title' => $meeting->title,
                'date' => $meeting->scheduled_at?->translatedFormat('d M Y') ?? '—',
            ]));

        if ($minutes?->announcements) {
            $mail->line('---')->line('**' . __('Announcements') . '**');
            foreach ($minutes->announcements as $ann) {
                $mail->line('• ' . $ann);
            }
        }

        if ($minutes?->decisions) {
            $mail->line('---')->line('**' . __('Decisions') . '**');
            foreach ($minutes->decisions as $dec) {
                $mail->line('• ' . $dec);
            }
        }

        return $mail
            ->action(__('View full minutes'), $this->meetingUrl($notifiable, $meeting))
            ->salutation(__('Regards,'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
