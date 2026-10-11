<?php

declare(strict_types=1);

namespace App\Console\Commands\Season;

use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('season:provision')]
#[Description('Ensure the next two seasons exist beyond the current active season. Safe to run any time.')]
class ProvisionNextSeasonCommand extends Command
{
    public function handle(): int
    {
        // Use the active season as reference; fall back to the latest if none is active.
        $reference = Season::where('is_active', true)->first()
            ?? Season::orderByDesc('start_at')->first();

        // When no seasons exist, bootstrap from today's date.
        // Offsets [1,2,3] relative to (currentYear-1) = current season + 2 ahead.
        [$referenceStartYear, $offsets] = $reference
            ? [(int) $reference->start_at->format('Y'), [1, 2]]
            : [$this->currentSeasonStartYear() - 1, [1, 2, 3]];

        $created = 0;

        foreach ($offsets as $offset) {
            $nextStartYear = $referenceStartYear + $offset;
            $name = $nextStartYear . '-' . ($nextStartYear + 1);

            if (Season::where('name', $name)->exists()) {
                $this->line("Season {$name} already exists, skipping.");

                continue;
            }

            [$startAt, $endAt] = Season::defaultDates($nextStartYear);

            try {
                Season::create([
                    'name' => $name,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'is_active' => false,
                    'affiliations_open' => false,
                ]);

                $this->info("Created season: {$name}");
                $created++;
            } catch (\DomainException $e) {
                // A season covering these dates already exists under a different name.
                $this->warn("Skipped {$name}: {$e->getMessage()}");
            }
        }

        $this->info($created === 0 ? 'No new seasons needed.' : "{$created} season(s) provisioned.");

        return Command::SUCCESS;
    }

    private function currentSeasonStartYear(): int
    {
        return Season::defaultStartYearOf(now());
    }
}
