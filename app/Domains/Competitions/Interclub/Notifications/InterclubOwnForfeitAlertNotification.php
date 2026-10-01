<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\Competitions\Interclub\Models\InterclubChange;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * The federation has recorded a forfeit of one of our teams.
 *
 * Sent to the interclubs duty so a forfeit nobody declared is caught before
 * the fine: the federation has filed one on the wrong side before.
 */
class InterclubOwnForfeitAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param array<int, int> $changeIds */
    public function __construct(public readonly array $changeIds) {}

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('The federation recorded a forfeit of our team'),
            'body' => __('Check that it was declared by the club.'),
            'url' => route('admin.interclubs.interclubs'),
            'category' => 'interclub',
            'icon' => 'o-exclamation-triangle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $changes = InterclubChange::with(['interclub.visitedTeam.club', 'interclub.visitingTeam.club'])
            ->whereKey($this->changeIds)
            ->orderBy('id')
            ->get();

        $mail = (new MailMessage)
            ->subject(__('The federation recorded a forfeit of our team'))
            ->line(__('The federation has recorded the forfeit of one of our teams. The team has been told.'))
            ->line(__('If the club did not declare it, contact the federation before the fine is issued.'));

        foreach ($changes as $change) {
            $fixture = $change->interclub;
            $mail->line('• ' . trim(($fixture->ourTeam()?->fullName() ?? '—') . ' vs ' . ($fixture->opponentTeam()?->fullName() ?? '—'))
                . ' — ' . Carbon::parse($fixture->start_date_time)->format('d/m/Y H:i')
                . ' — ' . ($change->forfeit?->label() ?? ''));
        }

        return $mail->action(__('See the schedule'), route('admin.interclubs.interclubs'));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }
}
