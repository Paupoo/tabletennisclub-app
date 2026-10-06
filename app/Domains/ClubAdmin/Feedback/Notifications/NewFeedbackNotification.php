<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Feedback\Notifications;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackEntry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A member wrote to the committee through the permanent box. Rare enough to
 * deserve a mail each time; the yearly survey, which brings dozens at once,
 * sends none of these.
 */
class NewFeedbackNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public FeedbackEntry $entry) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('New feedback: :theme', ['theme' => $this->entry->theme->name]),
            'body' => $this->authorName(),
            'url' => route('admin.feedback.index'),
            'category' => 'feedback',
            'icon' => 'o-chat-bubble-left-ellipsis',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New feedback: :theme', ['theme' => $this->entry->theme->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__('From: :author', ['author' => $this->authorName()]))
            ->line($this->entry->body)
            ->action(__('Read the feedback'), route('admin.feedback.index'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    private function authorName(): string
    {
        return $this->entry->author?->full_name ?? __('Anonymous');
    }
}
