<?php

declare(strict_types=1);

namespace App\Console\Commands\Bar;

use App\Domains\Bar\Services\RestockingAutomation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Friday morning: the products in automatic mode take the min and max their sales suggest.
 *
 * It runs once Thursday evening is closed — a business day ends at 6 am — so the
 * week's last sales are counted, and before the Saturday digest, which reports
 * what moved.
 */
#[Signature('bar:restocking-recalculate')]
#[Description('Set the min and max of the bar products in automatic mode from their sales.')]
class RecalculateBarRestockingCommand extends Command
{
    public function handle(RestockingAutomation $automation): int
    {
        $adjustments = $automation->recalculate();

        $this->components->info(count($adjustments) . ' product(s) adjusted.');

        return self::SUCCESS;
    }
}
