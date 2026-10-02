<?php

declare(strict_types=1);

namespace App\Console\Commands\Interclub;

use App\Domains\Competitions\Interclub\Services\InterclubPlayedRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recompute who played every tie from the match sheets already on file.
 *
 * The daily `interclubs:import-results` does this for each sheet it reads. This
 * command exists for the sheets imported before it did: it reads our own
 * `interclub_individual_matches`, never the federation, so it is instant and
 * can be run again at will. A tie with no sheet on file is left as it is.
 */
#[Signature('interclubs:record-who-played')]
#[Description('Recompute who played each interclub match from the AFTT match sheets already imported.')]
class RecordWhoPlayedCommand extends Command
{
    public function handle(InterclubPlayedRecorder $recorder): int
    {
        $count = $recorder->recordAll();

        $this->info(sprintf('Recomputed who played %d match(es) from their sheet.', $count));

        return self::SUCCESS;
    }
}
