<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Interclub\Notifications;

use App\Data\Interclub\WeeklyFixture;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * « Sélections de la semaine » (2026-10-11) : the Thursday mail, to one
 * address. The same tiles as the page; those of the members this address
 * speaks for carry the club border. A snapshot, never resent: the page is the
 * live version, and the targeted lineup mails tell whoever a change concerns.
 */
class WeeklySelectionsNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<string, Collection<int, WeeklyFixture>>  $days
     * @param  array<int, int>  $memberIds  the members this address speaks for
     */
    public function __construct(
        public readonly Collection $days,
        public readonly array $memberIds,
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $period = __('from :from to :to', ['from' => $this->from->translatedFormat('j F'), 'to' => $this->to->translatedFormat('j F')]);

        return (new MailMessage)
            ->subject(__('Weekly selections') . ' · ' . $period)
            ->markdown('mail.interclub.weekly-selections', [
                'days' => $this->days,
                'memberIds' => $this->memberIds,
                'period' => $period,
                'url' => route('admin.user.weekly-selections'),
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
