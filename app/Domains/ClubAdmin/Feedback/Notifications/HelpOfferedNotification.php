<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Notifications;

use App\Domains\ClubAdmin\Feedback\Models\HelpOffer;
use App\Domains\ClubAdmin\Feedback\Models\HelpTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A member offers to give a hand. Told at once: a volunteer left three months
 * without news does not offer twice.
 */
class HelpOfferedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public HelpOffer $offer) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __(':name offers to help', ['name' => $this->offer->volunteer->full_name]),
            'body' => $this->tasks(),
            'url' => route('admin.feedback.index', ['tab' => 'help']),
            'category' => 'feedback',
            'icon' => 'o-heart',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__(':name offers to help', ['name' => $this->offer->volunteer->full_name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__(':name offers to give the club a hand (:rhythm).', [
                'name' => $this->offer->volunteer->full_name,
                'rhythm' => mb_strtolower($this->offer->rhythm->label()),
            ]));

        if ($this->tasks() !== '') {
            $mail->line(__('Tasks: :tasks', ['tasks' => $this->tasks()]));
        }

        if (filled($this->offer->message)) {
            $mail->line($this->offer->message);
        }

        return $mail->action(__('Follow up on the offer'), route('admin.feedback.index', ['tab' => 'help']));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function tasks(): string
    {
        return $this->offer->tasks->map(fn (HelpTask $task): string => $task->name)->implode(', ');
    }
}
