<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One sync changed too much at once: nothing reached the teams.
 *
 * The calendar is already up to date — only the messages wait. They go out
 * from the review screen once somebody has checked them against what the
 * federation said.
 */
class InterclubChangesHeldNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $count) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Federation changes held for review'),
            'body' => __(':count changes in one run: no team has been told yet.', ['count' => $this->count]),
            'url' => route('admin.interclubs.changes'),
            'category' => 'interclub',
            'icon' => 'o-pause-circle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Federation changes held for review'))
            ->line(__('The federation changed :count of our fixtures in one go. The calendar is up to date, but no team has been told.', ['count' => $this->count]))
            ->line(__('Check the changes against what the federation announced, then tell the teams from the review screen.'))
            ->action(__('Review the changes'), route('admin.interclubs.changes'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
