<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Enums\MeetingFormatEnum;
use App\Models\ClubEvents\Meeting\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class MeetingInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Meeting $meeting) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'meeting_id' => $this->meeting->id,
            'type' => 'meeting_invitation',
            'title' => $this->meeting->title,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting;
        $meeting->loadMissing('agendaItems');

        $mail = (new MailMessage)
            ->subject(__('Invitation: :title — :date', [
                'title' => $meeting->title,
                'date' => $meeting->scheduled_at?->format('d/m/Y') ?? '—',
            ]))
            ->greeting(__('Hi :name!', ['name' => $notifiable->first_name]))
            ->line(__('You are invited to **:title**.', ['title' => $meeting->title]));

        $mail->line('---');
        $mail->line(__('**Date:** :date at :time', [
            'date' => $meeting->scheduled_at?->translatedFormat('l d M Y') ?? '—',
            'time' => $meeting->scheduled_at?->format('H:i') ?? '—',
        ]));

        if ($meeting->format === MeetingFormatEnum::PHYSICAL && $meeting->location) {
            $mail->line(__('**Location:** :location', ['location' => $meeting->location]));
        } elseif ($meeting->format === MeetingFormatEnum::VIRTUAL && $meeting->meeting_link) {
            $mail->line(__('**Meeting link:** :link', ['link' => $meeting->meeting_link]));
        }

        if ($meeting->rsvp_deadline) {
            $mail->line(__('**RSVP deadline:** :date', [
                'date' => $meeting->rsvp_deadline->translatedFormat('d M Y'),
            ]));
        }

        if ($meeting->agendaItems->isNotEmpty()) {
            $mail->line('---');
            $mail->line('**' . __('Agenda') . '**');
            foreach ($meeting->agendaItems as $i => $item) {
                $mail->line(($i + 1) . '. ' . $item->title);
            }
        }

        if ($meeting->has_meal && $meeting->meal_description) {
            $mail->line('---');
            $mail->line(__('**Meal:** :desc', ['desc' => $meeting->meal_description]));
            if ($meeting->meal_price) {
                $mail->line(__('**Cost:** :price €/person', ['price' => number_format($meeting->meal_price, 2)]));
            }
        }

        if ($meeting->quorum) {
            $mail->line('---');
            $mail->line(__('**Quorum required:** :n members must confirm their attendance.', ['n' => $meeting->quorum]));
        }

        $confirmUrl = URL::signedRoute(
            'meetings.rsvp',
            ['meeting' => $meeting->id, 'user' => $notifiable->id, 'response' => 'confirmed'],
            now()->addDays(30),
        );
        $declineUrl = URL::signedRoute(
            'meetings.rsvp',
            ['meeting' => $meeting->id, 'user' => $notifiable->id, 'response' => 'declined'],
            now()->addDays(30),
        );

        return $mail
            ->action(__('I will attend'), $confirmUrl)
            ->line('[' . __('I cannot attend') . '](' . $declineUrl . ')')
            ->salutation(__('See you there!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
