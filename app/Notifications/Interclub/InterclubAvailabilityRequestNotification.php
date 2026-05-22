<?php

declare(strict_types=1);

namespace App\Notifications\Interclub;

use App\Models\ClubEvents\Interclub\Interclub;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InterclubAvailabilityRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Interclub $interclub,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'interclub_id' => $this->interclub->id,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $interclub = $this->interclub;
        $interclub->loadMissing(['visitedTeam', 'visitingTeam', 'visitedTeam.club', 'visitingTeam.club']);

        $ourTeam = $interclub->visitedTeam?->club?->licence === config('app.club_licence')
            ? $interclub->visitedTeam
            : $interclub->visitingTeam;

        $opponent = $interclub->visitedTeam?->id === $ourTeam?->id
            ? ($interclub->visitingTeam?->name ?? '—')
            : ($interclub->visitedTeam?->name ?? '—');

        $dateStr = $interclub->start_date_time->format('d/m/Y');
        $teamName = $ourTeam?->name ?? '—';

        $url = route('admin.interclubs.my-matches');

        return (new MailMessage)
            ->subject(__('Your availability needed — :team vs :opponent on :date', [
                'team' => $teamName,
                'opponent' => $opponent,
                'date' => $dateStr,
            ]))
            ->greeting(__('Hello :name!', ['name' => $notifiable->first_name]))
            ->line(__('Your captain needs to know if you are available for the upcoming interclub match.'))
            ->line('---')
            ->line(__('**Date:** :date', ['date' => $dateStr]))
            ->line(__('**Opponent:** :opponent', ['opponent' => $opponent]))
            ->line(__('**Team:** :team', ['team' => $teamName]))
            ->action(__('Indicate my availability'), $url)
            ->salutation(__('Thank you!'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
