<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Notifications;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Meetings\Models\Meeting;
use App\Services\IcsGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class MeetingRsvpConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Meeting $meeting,
        public ?Payment $payment = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Présence confirmée: :title', ['title' => $this->meeting->title]),
            'body' => __('Votre présence à la réunion du :date est confirmée', ['date' => $this->meeting->scheduled_at?->translatedFormat('d M Y') ?? __('TBD')]),
            'url' => route('admin.meetings.show', $this->meeting),
            'category' => 'meeting',
            'icon' => 'o-calendar-days',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting;
        $meeting->loadMissing('agendaItems');

        $qrCode = null;
        if ($this->payment) {
            $qrCode = (new GeneratePaymentQR)($this->payment);
        }

        $ics = app(IcsGenerator::class)->forMeeting($meeting);

        return (new MailMessage)
            ->markdown('mail.meeting-rsvp-confirmation', [
                'user' => $notifiable,
                'meeting' => $meeting,
                'payment' => $this->payment,
                'qrCode' => $qrCode,
                'club' => Club::ourClub()->first(),
            ])
            ->subject(__('Attendance confirmed: :title', ['title' => $meeting->title]))
            ->attachData($ics, 'meeting.ics', ['mime' => 'text/calendar']);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
