<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Notifications;

use App\Domains\Shared\Enums\TrainingCancellationType;
use App\Domains\Trainings\Models\Training;
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
            'title' => __('Training session cancelled'),
            'body' => __('See the training details'),
            'url' => route('admin.trainings.index'),
            'category' => 'training',
            'icon' => 'o-academic-cap',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pack = $this->training->trainingPack;
        $date = $this->training->start->translatedFormat('l d F Y à H:i');

        $message = (new MailMessage)
            ->subject(__('Session cancelled — :pack', ['pack' => $pack?->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('The session on :date for pack **:pack** has been cancelled.', [
                'date' => $date,
                'pack' => $pack?->name,
            ]));

        if ($this->cancellationType === TrainingCancellationType::FREE) {
            $message->line(__('The room stays open for free practice.'));
        } else {
            $message->line(__('The room will be closed that day.'));
        }

        if ($this->note) {
            $message->line(__('Note : :note', ['note' => $this->note]));
        }

        return $message->line(__('The cost of this session remains covered by your training pack.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
