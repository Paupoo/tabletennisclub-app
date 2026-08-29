<?php

declare(strict_types=1);

namespace App\Console\Commands\Tournament;

use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\States\Tournament\TournamentStateMachine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('tournament:close-registrations')]
#[Description('Transition published tournaments whose registration deadline has passed to setup status.')]
class CloseRegistrationsByDeadlineCommand extends Command
{
    public function handle(): int
    {
        $tournaments = Tournament::registrationsOpen()
            ->whereNotNull('registration_deadline')
            ->where('registration_deadline', '<', now())
            ->get();

        $closed = 0;

        foreach ($tournaments as $tournament) {
            try {
                (new TournamentStateMachine($tournament))->setUp();
            } catch (InvalidArgumentException $e) {
                // A tournament nobody joined cannot be closed for setup; it has
                // to be cancelled instead. Say so and keep going rather than
                // aborting the whole run on one of them.
                $this->warn("Left open: {$tournament->name} (ID {$tournament->id}) — {$e->getMessage()}");

                continue;
            }

            $this->info("Closed: {$tournament->name} (ID {$tournament->id})");
            $closed++;
        }

        $this->info("Done — {$closed} tournament(s) closed.");

        return self::SUCCESS;
    }
}
