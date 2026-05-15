<?php

declare(strict_types=1);

namespace App\Console\Commands\Tournament;

use App\Enums\TournamentStatusEnum;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tournament:close-registrations')]
#[Description('Transition published tournaments whose registration deadline has passed to setup status.')]
class CloseRegistrationsByDeadlineCommand extends Command
{
    public function handle(): int
    {
        $tournaments = Tournament::where('status', TournamentStatusEnum::PUBLISHED)
            ->whereNotNull('registration_deadline')
            ->where('registration_deadline', '<', now())
            ->get();

        foreach ($tournaments as $tournament) {
            $tournament->update(['status' => TournamentStatusEnum::SETUP]);
            $this->info("Closed: {$tournament->name} (ID {$tournament->id})");
        }

        $this->info("Done — {$tournaments->count()} tournament(s) closed.");

        return self::SUCCESS;
    }
}
