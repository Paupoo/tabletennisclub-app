<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Sends every error a command prints to the application log as well.
 *
 * The scheduler throws a command's output away and records only its exit
 * code. For the federation imports, which run every hour, that meant a log
 * reading "failed with exit code [1]" sixteen times a day and nothing about
 * why: in October 2026 the reason, a TLS failure on the federation's host, sat
 * in a message nobody could read.
 */
trait LogsErrors
{
    /**
     * @param  string  $string
     * @param  int|string|null  $verbosity
     */
    public function error($string, $verbosity = null): void
    {
        parent::error($string, $verbosity);

        Log::error(trim((string) $string), ['command' => $this->getName()]);
    }
}
