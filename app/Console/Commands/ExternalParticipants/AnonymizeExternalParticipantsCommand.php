<?php

declare(strict_types=1);

namespace App\Console\Commands\ExternalParticipants;

use App\Domains\ClubAdmin\ExternalParticipants\Services\ExternalParticipantErasure;
use Illuminate\Console\Command;

/**
 * Erases the identity of non-members six months after their event ended.
 *
 * Run every night. A registration with money still open is left for the
 * treasurer and erased the night after it is settled.
 */
class AnonymizeExternalParticipantsCommand extends Command
{
    protected $description = 'Erase who non-members were, six months after their event ended';

    protected $signature = 'external-participants:anonymize';

    public function handle(ExternalParticipantErasure $erasure): int
    {
        $erased = $erasure->run();
        $heldBack = $erasure->heldBack()->count();

        $this->components->info(trans_choice(
            '{0}No external participant to anonymise.|[1,*]:count external participant(s) anonymised.',
            $erased,
            ['count' => $erased],
        ));

        if ($heldBack > 0) {
            $this->components->warn(trans_choice(
                '{1}One external participant waits: money is still open.|[2,*]:count external participants wait: money is still open.',
                $heldBack,
                ['count' => $heldBack],
            ));
        }

        return self::SUCCESS;
    }
}
