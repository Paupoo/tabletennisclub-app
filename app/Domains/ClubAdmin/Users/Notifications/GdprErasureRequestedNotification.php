<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Users\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GdprErasureRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $member) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('GDPR erasure requested'),
            'body' => __(':name requested the erasure of their personal data.', ['name' => $this->member->full_name]),
            'url' => route('admin.users.edit', $this->member),
            'category' => 'gdpr',
            'icon' => 'o-shield-exclamation',
            'member_id' => $this->member->id,
            'member_name' => $this->member->full_name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('GDPR erasure requested — action needed'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))
            ->line(__(':name requested the erasure of their personal data.', ['name' => $this->member->full_name]))
            ->line(__('Please review the request and anonymize the account from the member page.'))
            ->action(__('Review the request'), route('admin.users.edit', $this->member))
            ->line(__('Reminder: you have one month to process an erasure request.'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
