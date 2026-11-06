<?php

declare(strict_types=1);

namespace App\Support\AutomaticEmails;

use App\Domains\Shared\Enums\EmailTrigger;
use App\Domains\Shared\Enums\Feature;

/**
 * One email the application sends by itself, as the catalogue describes it.
 *
 * Everything here is written by hand except what the schedule knows: a
 * scheduled email names its commands, and its cadence, next run and state are
 * read from the live schedule rather than copied — {@see ScheduledRuns}.
 */
final readonly class AutomaticEmail
{
    /**
     * @param  class-string  $source  The notification or mailable — or, for the one email built inline, the class that builds it.
     * @param  string|null  $when  For an event or an action, what sets it off. For a scheduled email, another way it also leaves, if any.
     * @param  string|null  $condition  What has to be true for it to actually leave, when anything has to.
     * @param  array<int, string>  $commands  The scheduled commands that send it, arguments included, as written in routes/console.php.
     * @param  string|null  $cadence  Says the cadence in words when the cron alone would mislead — a monthly entry filtered down to quarters.
     * @param  string|null  $optOut  The member preference that silences it, when one does.
     */
    public function __construct(
        public string $source,
        public string $name,
        public string $domain,
        public EmailTrigger $trigger,
        public string $recipients,
        public ?string $when = null,
        public ?string $condition = null,
        public array $commands = [],
        public ?string $cadence = null,
        public ?Feature $feature = null,
        public ?string $optOut = null,
    ) {}

    public function isOff(): bool
    {
        return $this->feature?->disabled() ?? false;
    }
}
