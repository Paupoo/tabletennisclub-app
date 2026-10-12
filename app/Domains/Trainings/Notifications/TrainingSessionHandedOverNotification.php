<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Trainings\Models\Training;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient celui à qui l'on confie une séance : il en devient le coach, avec
 * le pointage et les coordonnées des inscrits.
 */
class TrainingSessionHandedOverNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Training $training) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('A training session was handed over to you'),
            'body' => $this->training->start->translatedFormat('l d F Y à H:i'),
            'url' => route('coach.trainings'),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pack = $this->training->trainingPack;

        return (new MailMessage)
            ->subject(__('Session to coach — :pack', ['pack' => $pack?->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('You coach the session on :date for pack **:pack**.', [
                'date' => $this->training->start->translatedFormat('l d F Y à H:i'),
                'pack' => $pack?->name,
            ]))
            ->action(__('Open my coach area'), route('coach.trainings'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
