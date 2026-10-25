<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Trainings\Models\Training;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient l'encadrement d'une séance annulée — sparrings, coach remplaçant —
 * qui se déplacerait sinon pour rien. Les inscrits ont leur propre message.
 */
class TrainingSessionCancelledForStaffNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Training $training) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Training session cancelled'),
            'body' => $this->training->start->translatedFormat('l d F Y à H:i'),
            'url' => route('admin.user.calendar', $notifiable),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pack = $this->training->trainingPack;

        $message = (new MailMessage)
            ->subject(__('Session cancelled — :pack', ['pack' => $pack?->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('The session on :date for pack **:pack** has been cancelled. You are not expected.', [
                'date' => $this->training->start->translatedFormat('l d F Y à H:i'),
                'pack' => $pack?->name,
            ]));

        if ($this->training->cancellation_note) {
            $message->line(__('Note : :note', ['note' => $this->training->cancellation_note]));
        }

        return $message;
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable->emails_notifications ? ['mail', 'database'] : ['database'];
    }
}
