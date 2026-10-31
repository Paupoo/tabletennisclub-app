<?php

declare(strict_types=1);

namespace App\Support\AutomaticEmails;

use App\Domains\Shared\Enums\EmailTrigger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The catalogue as the page reads it (decided on 2026-10-31).
 *
 * Scheduled emails first: those whose task runs several times a day apart,
 * since a deadline sends them, not the clock; then each of the others once, at
 * its next run, day by day over a week, and the rest « later ». The emails an
 * event or an action sets off follow, by domain. A domain switched off stays
 * on the page, marked — hidden, the email would read as never written.
 */
final readonly class AutomaticEmailAgenda
{
    public const int DAYS_AHEAD = 7;

    public function __construct(private ScheduledRuns $runs) {}

    /**
     * @param  array<int, AutomaticEmail>  $emails
     * @return array{
     *     continuous: array<int, array{email: AutomaticEmail, cadence: string}>,
     *     days: array<int, array{date: CarbonImmutable, runs: array<int, array{email: AutomaticEmail, cadence: string, at: CarbonImmutable}>}>,
     *     later: array<int, array{email: AutomaticEmail, cadence: string, at: ?CarbonImmutable}>,
     *     domains: array<int, array{name: string, off: bool, emails: array<int, AutomaticEmail>}>,
     * }
     */
    public function build(array $emails, ?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $horizon = $now->startOfDay()->addDays(self::DAYS_AHEAD);

        $continuous = [];
        $dated = [];
        $later = [];
        $domains = [];

        foreach ($emails as $email) {
            if ($email->trigger !== EmailTrigger::Scheduled) {
                $domains[$email->domain] ??= ['name' => $email->domain, 'off' => $email->isOff(), 'emails' => []];
                $domains[$email->domain]['emails'][] = $email;

                continue;
            }

            $cadence = $this->runs->cadence($email);

            if ($this->runs->isContinuous($email, $now)) {
                $continuous[] = ['email' => $email, 'cadence' => $cadence];

                continue;
            }

            $at = $email->isOff() ? null : $this->runs->nextRun($email, $now);

            if ($at !== null && $at->lt($horizon)) {
                $dated[] = ['email' => $email, 'cadence' => $cadence, 'at' => $at];
            } else {
                $later[] = ['email' => $email, 'cadence' => $cadence, 'at' => $at];
            }
        }

        usort($dated, fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        usort($later, fn (array $a, array $b): int => [$a['at'] === null, $a['at']] <=> [$b['at'] === null, $b['at']]);

        $days = [];

        foreach ($dated as $run) {
            $key = $run['at']->toDateString();
            $days[$key] ??= ['date' => $run['at']->startOfDay(), 'runs' => []];
            $days[$key]['runs'][] = $run;
        }

        return [
            'continuous' => $continuous,
            'days' => array_values($days),
            'later' => $later,
            'domains' => array_values($domains),
        ];
    }
}
