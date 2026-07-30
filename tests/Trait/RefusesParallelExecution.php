<?php

declare(strict_types=1);

namespace Tests\Trait;

use PHPUnit\Framework\Attributes\Before;

/**
 * Browser tests are not parallel-safe: under `--parallel` they race for the
 * test server, time out, and report "Unauthenticated" on a different test each
 * run, so the failure reads as a regression in whatever was being worked on.
 *
 * Fail immediately with an actionable message rather than let the suite emit an
 * intermittent red. `composer test` already runs them in their own sequential
 * pass; this only catches a bare `php artisan test --parallel`, which picks up
 * every suite including this one.
 */
trait RefusesParallelExecution
{
    /**
     * Read TEST_TOKEN rather than the ParallelTesting facade: #[Before] hooks
     * run ahead of setUp(), so no facade root is bound yet.
     */
    #[Before]
    public function refuseParallelExecution(): void
    {
        if (! isset($_SERVER['TEST_TOKEN'])) {
            return;
        }

        $this->fail(
            'Browser tests do not support --parallel: they race for the test server and fail at random. '
            . 'Run `composer test`, or `php artisan test --testsuite=Browser` without --parallel.'
        );
    }
}
