<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubChange;
use App\Domains\Shared\Enums\InterclubChangeKind;
use App\Domains\Shared\Enums\InterclubForfeit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * What the federation changed about a fixture of the reader's team.
 *
 * One message per change, except a withdrawal: the fixtures it cancels arrive
 * together, because the news is the withdrawal and two mails the same morning
 * for the same cause read as a mistake.
 *
 * The call to action is always the captain, as soon as possible. Nothing here
 * asks the reader to change an answer: by the time the federation moves a
 * fixture it is too late for the club to recompose through a form.
 */
class InterclubChangeNotification extends Notification
{
    use Queueable;

    /** @param Collection<int, InterclubChange> $changes */
    public function __construct(
        public readonly Collection $changes,
        public readonly bool $forCaptain = false,
        public readonly int $informedCount = 0,
    ) {}

    /**
     * The words a reader sees first: what happened, to whom, when.
     */
    public function subject(): string
    {
        $change = $this->changes->first();
        $fixture = $change->interclub;
        $words = $this->words($fixture);

        return match (true) {
            $change->kind === InterclubChangeKind::FORFEIT_LIFTED => __('Match back on — :team vs :opponent on :date', $words),
            $change->kind === InterclubChangeKind::RESCHEDULED => __('Match moved — :team vs :opponent', $words),
            $change->forfeit === InterclubForfeit::OPPONENT_WITHDRAWAL => __(':opponent has withdrawn from the division — :team', $words),
            $change->forfeit === InterclubForfeit::OUR_WITHDRAWAL => __(':team withdrawn from the division', $words),
            $change->forfeit === InterclubForfeit::OUR_FORFEIT => __('Forfeit of :team — :date', $words),
            default => __('Match cancelled — :team vs :opponent on :date', $words),
        };
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->subject(),
            'body' => __('Contact your captain as soon as possible if you have a question or a problem.'),
            'url' => route('admin.interclubs.my-match', $this->changes->first()->interclub_id),
            'category' => 'interclub',
            'icon' => 'o-exclamation-triangle',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $first = $this->changes->first();
        $fixture = $first->interclub;
        $captain = $fixture->ourTeam()?->captain;

        return (new MailMessage)
            ->subject($this->subject())
            ->markdown('mail.interclub.change', [
                'notifiable' => $notifiable,
                'headline' => $this->subject(),
                'lead' => $this->lead($first),
                'rows' => $this->changes->map(fn (InterclubChange $change): array => $this->row($change))->all(),
                'isReschedule' => $first->kind === InterclubChangeKind::RESCHEDULED,
                'forCaptain' => $this->forCaptain,
                'informedCount' => $this->informedCount,
                'captain' => $this->forCaptain ? null : $captain,
                'url' => route('admin.interclubs.my-match', $fixture),
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        // Même préférence que le mail de compo : c'est la même audience.
        if ($notifiable instanceof User && ! $notifiable->wantsNotification('interclub_selections')) {
            return [];
        }

        return ['mail', 'database'];
    }

    private function lead(InterclubChange $change): string
    {
        $words = $this->words($change->interclub);

        return match (true) {
            $change->kind === InterclubChangeKind::FORFEIT_LIFTED => __('The federation has withdrawn the forfeit: the match :team vs :opponent will be played after all.', $words),
            $change->kind === InterclubChangeKind::RESCHEDULED => __('The federation has changed the match :team vs :opponent.', $words),
            $change->forfeit === InterclubForfeit::OPPONENT_WITHDRAWAL => __(':opponent has withdrawn from the division: these matches will not be played.', $words),
            $change->forfeit === InterclubForfeit::OUR_WITHDRAWAL => __('The federation has recorded the withdrawal of :team from the division: these matches will not be played.', $words),
            $change->forfeit === InterclubForfeit::OUR_FORFEIT => __('The federation has recorded the forfeit of :team: the match against :opponent will not be played.', $words),
            default => __(':opponent has forfeited: the match :team vs :opponent will not be played.', $words),
        };
    }

    /**
     * @param  array{start: string|null, address: string|null}|null  $moment
     */
    private function moment(?array $moment): string
    {
        $start = $moment['start'] ?? null;

        $when = $start === null
            ? '—'
            : Carbon::parse($start)->translatedFormat('l j F Y') . ' ' . __('at') . ' ' . Carbon::parse($start)->format('H:i');

        return trim($when . (($moment['address'] ?? '') !== '' ? ' — ' . $moment['address'] : ''));
    }

    /**
     * One fixture as the mail lists it: the moment and the hall before and after.
     *
     * @return array{before: string, after: string}
     */
    private function row(InterclubChange $change): array
    {
        return [
            'before' => $this->moment($change->before),
            'after' => $this->moment($change->after),
        ];
    }

    /**
     * @return array{team: string, opponent: string, date: string}
     */
    private function words(Interclub $fixture): array
    {
        return [
            'team' => $fixture->ourTeam()?->fullName() ?? '—',
            'opponent' => $fixture->opponentTeam()?->fullName() ?? '—',
            'date' => $fixture->start_date_time->format('d/m/Y'),
        ];
    }
}
