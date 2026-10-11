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
     * @param  array<int, string>  $names  first names of whoever reads this address
     */
    public function __construct(
        public readonly Collection $days,
        public readonly array $memberIds,
        public readonly CarbonInterface $from,
        public readonly CarbonInterface $to,
        public readonly array $names = [],
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $period = __('from :from to :to', ['from' => $this->from->translatedFormat('j F'), 'to' => $this->to->translatedFormat('j F')]);

        return (new MailMessage)
            ->subject(__('Weekly selections') . ' · ' . $period)
            ->markdown('mail.interclub.weekly-selections', [
                'days' => $this->days,
                'memberIds' => $this->memberIds,
                'greeting' => $this->greeting(),
                'intro' => $this->intro($period),
                'url' => route('admin.user.weekly-selections'),
            ]);
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    private function greeting(): string
    {
        return match (count($this->names)) {
            0 => __('Hello,'),
            1 => __('Hello :name,', ['name' => $this->names[0]]),
            default => __('Hello :names,', ['names' => implode(', ', array_slice($this->names, 0, -1)) . ' ' . __('and') . ' ' . array_last($this->names)]),
        };
    }

    /**
     * The opening line names the competition week — or weeks, when a match on
     * the Monday after opens the next one.
     */
    private function intro(string $period): string
    {
        $weeks = $this->days->flatten()
            ->map(fn (WeeklyFixture $fixture): ?int => $fixture->matchDay)
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return match ($weeks->count()) {
            0 => __('Here are the selections for the matches :period.', ['period' => $period]),
            1 => __('Here are the selections for the matches of competition week :weeks, :period.', ['weeks' => $weeks->first(), 'period' => $period]),
            default => __('Here are the selections for the matches of competition weeks :weeks, :period.', [
                'weeks' => $weeks->slice(0, -1)->join(', ') . ' ' . __('and') . ' ' . $weeks->last(),
                'period' => $period,
            ]),
        };
    }
}
