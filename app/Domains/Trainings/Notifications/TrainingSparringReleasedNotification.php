<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient un sparring qu'on ne l'attend plus : aux séances à venir d'un
 * pack, ou à une seule date.
 */
class TrainingSparringReleasedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ?TrainingPack $pack = null,
        public readonly ?Training $session = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('You are no longer expected as a sparring'),
            'body' => $this->summary(),
            'url' => route('admin.user.calendar', $notifiable),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Sparring — :pack', ['pack' => $this->packName()]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line($this->summary());
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable->emails_notifications ? ['mail', 'database'] : ['database'];
    }

    private function packName(): ?string
    {
        return $this->session?->trainingPack?->name ?? $this->pack?->name;
    }

    private function summary(): string
    {
        if ($this->session instanceof Training) {
            return __('You are no longer expected as a sparring at the session on :date for pack **:pack**.', [
                'date' => $this->session->start->translatedFormat('l d F Y à H:i'),
                'pack' => $this->packName(),
            ]);
        }

        return __('You are no longer expected as a sparring at the coming sessions of pack **:pack**.', [
            'pack' => $this->packName(),
        ]);
    }
}
