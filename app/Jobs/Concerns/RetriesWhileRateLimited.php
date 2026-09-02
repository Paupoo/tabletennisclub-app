<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use DateTimeInterface;

/**
 * The retry policy a throttled mailing needs, carried by the job itself.
 *
 * Laravel's RateLimited middleware does not hold a job back: when the limiter is
 * full it releases the job to the tail of the queue, and a release counts as an
 * attempt. A mailing spread over a limiter is therefore attempted once per
 * window it waits through — the sixteenth invitation of a fifty member send
 * wakes up three times before its turn comes. Under a worker started with
 * `--tries=1`, which is what `composer dev` runs, each of those was killed on
 * its first return with MaxAttemptsExceededException, before handle() ever ran:
 * the throttle did not spread the mailing, it dropped everything past the first
 * fifteen, and dropped it silently.
 *
 * Hence a deadline rather than a count. Waiting for one's turn is not failing,
 * so the thing worth bounding is how long a message may stay undelivered. Only
 * the job knows it is throttled, and the worker's flags differ between
 * `composer dev` and the server — which is why this cannot live over there.
 */
trait RetriesWhileRateLimited
{
    /**
     * A minute between genuine retries.
     *
     * The limiter sets its own release delay when it holds a job back; this only
     * spaces out the retries that follow a real exception, so a mail server that
     * blinks is not spent three times in the same second.
     */
    public int $backoff = 60;

    /**
     * A real fault still fails fast.
     *
     * The deadline below overrides the attempt count entirely, so without this a
     * mail server refusing the connection would be retried for six hours. Only
     * the waiting is unlimited; three genuine exceptions and the job is done.
     */
    public int $maxExceptions = 3;

    /**
     * Stamped once, when the job is first dispatched.
     *
     * The worst realistic send — the whole club at fifteen a minute — takes well
     * under an hour. Six hours leaves room for a worker stopped overnight, and
     * past that a convocation arriving two days late is worse than one that
     * failed loudly enough to be seen in `failed_jobs`.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }
}
