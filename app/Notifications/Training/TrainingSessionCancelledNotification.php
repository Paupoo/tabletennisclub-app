<?php

declare(strict_types=1);

namespace App\Notifications\Training;

use App\Enums\TrainingCancellationType;
use App\Models\ClubEvents\Training\Training;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrainingSessionCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Training $training,
        public readonly TrainingCancellationType $cancellationType,
        public readonly ?string $note = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'training_id' => $this->training->id,
            'pack_name' => $this->training->trainingPack?->name,
            'cancelled_at' => $this->training->cancelled_at,
            'cancellation_type' => $this->cancellationType->value,
            'note' => $this->note,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pack = $this->training->trainingPack;
        $date = $this->training->start->translatedFormat('l d F Y à H:i');

        $message = (new MailMessage)
            ->subject(__('Séance annulée — :pack', ['pack' => $pack?->name]))
            ->greeting(__('Bonjour :name,', ['name' => $notifiable->first_name]))
            ->line(__('La séance du :date pour le pack **:pack** a été annulée.', [
                'date' => $date,
                'pack' => $pack?->name,
            ]));

        if ($this->cancellationType === TrainingCancellationType::FREE) {
            $message->line(__('La salle reste ouverte pour un entraînement libre.'));
        } else {
            $message->line(__('La salle sera inaccessible ce jour-là.'));
        }

        if ($this->note) {
            $message->line(__('Note : :note', ['note' => $this->note]));
        }

        return $message->line(__('Le coût de cette séance reste inclus dans votre abonnement annuel.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
