<?php

declare(strict_types=1);

namespace App\Domains\Meetings\Notifications;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingFormatEnum;
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

        $mail = (new MailMessage)
            ->subject(__('Attendance confirmed: :title', ['title' => $meeting->title]))
            ->greeting(__('Thank you, :name!', ['name' => $notifiable->first_name]))
            ->line(__('Your attendance to **:title** is confirmed.', ['title' => $meeting->title]));

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

        // ── Paiement repas ──────────────────────────────────────────────
        if ($this->payment) {
            $mail->line('---');
            $mail->line('**' . __('Meal payment') . '**');
            if ($meeting->meal_description) {
                $mail->line($meeting->meal_description);
            }
            $mail->line(__('**Amount due:** :amount €', [
                'amount' => number_format($this->payment->amount_due, 2, ',', ' '),
            ]));
            $mail->line(__('**Structured reference:** :ref', ['ref' => $this->payment->reference]));
            $mail->line(__('IBAN: BE23 7323 3320 8791 — BIC: CREGBEBB'));
            $mail->line(__('Beneficiary: CTT Ottignies-Blocry ASBL'));
        }

        $ics = app(IcsGenerator::class)->forMeeting($meeting);

        return $mail
            ->attachData($ics, 'meeting.ics', ['mime' => 'text/calendar'])
            ->salutation(__('See you there!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
