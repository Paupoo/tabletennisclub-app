<?php

declare(strict_types=1);

namespace App\Notifications\Tournament;

use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubPosts\NewsPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class TournamentInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tournament $tournament,
        public string $customMessage = '',
        public bool $includeArticleLink = false,
        public ?int $newsPostId = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->tournament;
        $t->loadMissing('rooms');

        $matchType = $t->match_type === 'double' ? __('Doubles') : __('Singles');
        $sets = __(':n winning sets (Best of :total)', [
            'n' => $t->sets_to_win,
            'total' => ($t->sets_to_win * 2) - 1,
        ]);
        $handicap = $t->has_handicap_points ? __('Yes') : __('No');
        $location = $t->rooms->pluck('name')->join(', ') ?: '—';

        $mail = (new MailMessage)
            ->subject(__('Invitation: :tournament — :date', [
                'tournament' => $t->name,
                'date' => $t->start_date?->format('d/m/Y') ?? '—',
            ]))
            ->greeting(__('Hi :name!', ['name' => $notifiable->first_name]))
            ->line(__('You are invited to participate in **:tournament**.', ['tournament' => $t->name]))
            ->line('---')
            ->line(__('**Date:** :date at :time', [
                'date' => $t->start_date?->format('d/m/Y') ?? '—',
                'time' => $t->start_time ?? '—',
            ]))
            ->line(__('**Location:** :rooms', ['rooms' => $location]))
            ->line(__('**Format:** :type — :sets', ['type' => $matchType, 'sets' => $sets]))
            ->line(__('**Handicap points:** :handicap', ['handicap' => $handicap]));

        if ($t->isPaid()) {
            $mail->line(__('**Entry fee:** :price €', ['price' => number_format((float) $t->price, 2)]));
            $mail->line(__('Payment details will be provided upon registration.'));
        }

        if ($t->registration_deadline) {
            $mail->line(__('**Registration deadline:** :date', [
                'date' => $t->registration_deadline->format('d/m/Y'),
            ]));
        }

        if (! empty($this->customMessage)) {
            $mail->line('---')->line($this->customMessage);
        }

        if ($this->includeArticleLink && $this->newsPostId !== null) {
            $newsPost = NewsPost::find($this->newsPostId);
            if ($newsPost) {
                $mail->action(__('Read the article'), route('public.clubPosts.show', $newsPost->slug));
            }
        }

        $registrationUrl = URL::signedRoute(
            'tournament.register.email',
            ['tournament' => $t->id, 'user' => $notifiable->id],
            now()->addDays(7),
        );

        return $mail
            ->action(__('I want to play'), $registrationUrl)
            ->line(__('We hope to see you there!'))
            ->salutation(__('See you on the court!'));
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
