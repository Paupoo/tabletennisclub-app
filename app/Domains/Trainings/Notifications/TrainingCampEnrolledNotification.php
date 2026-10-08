<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Shared\Traits\LinksToMemberSpace;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The member's spot on a stage is confirmed.
 *
 * Unlike a season pack, the price can be named here: the stage is invoiced on
 * its own, so the amount is exactly what this enrolment asks — never the
 * season total a family may already have paid. The payment request itself
 * leaves in its own mail, with the QR code.
 */
class TrainingCampEnrolledNotification extends Notification
{
    use LinksToMemberSpace, Queueable;

    public function __construct(
        public readonly TrainingPack $pack,
        public readonly float $amount,
        public readonly bool $byClub = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Enrolled in a training camp'),
            'body' => __('Your spot on :pack is confirmed', ['pack' => $this->pack->name]),
            'url' => $this->memberPackUrl($notifiable, $this->pack),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Training camp :pack — your spot is confirmed', ['pack' => $this->pack->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line($this->byClub
                ? __('The club has enrolled you in the training camp **:pack**, from :from to :to.', $this->dates())
                : __('Your enrolment in the training camp **:pack**, from :from to :to, is confirmed.', $this->dates()));

        if ($this->amount > 0) {
            $mail->line(__('The training camp is invoiced separately from your affiliation: **:amount €**. You will receive the payment request in a separate email.', [
                'amount' => number_format($this->amount, 2, ',', ' '),
            ]));
        }

        return $mail->action(__('See my registrations'), $this->memberPackUrl($notifiable, $this->pack));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * @return array{pack: string, from: string, to: string}
     */
    private function dates(): array
    {
        return [
            'pack' => $this->pack->name,
            'from' => $this->pack->pack_start_date?->format('d/m/Y') ?? '—',
            'to' => $this->pack->pack_end_date?->format('d/m/Y') ?? '—',
        ];
    }
}
