<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Notifications;

use App\Domains\Competitions\Tournament\Models\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TournamentUpdatedNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string>  $changes  Subset of: 'date', 'time', 'rooms'
     */
    public function __construct(
        public Tournament $tournament,
        public array $changes,
    ) {}

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('Tournament updated: :name', ['name' => $this->tournament->name]),
            'body' => __('A tournament has been updated'),
            'url' => route('admin.tournaments.index'),
            'category' => 'tournament',
            'icon' => 'o-trophy',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = array_filter([
            in_array('date', $this->changes) ? __('date') : null,
            in_array('time', $this->changes) ? __('time') : null,
            in_array('rooms', $this->changes) ? __('location') : null,
        ]);

        $mail = (new MailMessage)
            ->subject(__(':tournament — Important update', ['tournament' => $this->tournament->name]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('The following details of **:tournament** have changed: **:changes**.', [
                'tournament' => $this->tournament->name,
                'changes' => implode(', ', $labels),
            ]));

        if (in_array('date', $this->changes) || in_array('time', $this->changes)) {
            $mail->line(__('New date: **:date** at **:time**', [
                'date' => $this->tournament->start_date->format('d/m/Y'),
                'time' => $this->tournament->start_time ?? '—',
            ]));
        }

        if (in_array('rooms', $this->changes)) {
            $this->tournament->loadMissing('rooms');
            $mail->line(__('New location: **:rooms**', [
                'rooms' => $this->tournament->rooms->pluck('name')->join(', '),
            ]));
        }

        return $mail
            ->line(__('Please update your schedule accordingly. If you can no longer attend, contact the club.'))
            ->salutation(__('See you on the court!'));
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
