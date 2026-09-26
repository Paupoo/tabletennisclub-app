<?php

declare(strict_types=1);

namespace App\Console\Commands\Bar;

use App\Domains\Bar\Services\RestockingList;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Jobs\SendBarRestockingDigestJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The Saturday digest: to whoever may go shopping for the bar, what must be bought.
 *
 * Sent to every holder of the permission — the store keeper role and its
 * délégations alike — and only when something is at or below its min: no
 * "all is well" mail. It goes out even while a trip is in progress; the mail
 * says so, so nobody shops twice.
 */
#[Signature('bar:restocking-digest')]
#[Description('Mail whoever may do the bar shopping what must be bought.')]
class SendBarRestockingDigestCommand extends Command
{
    public function handle(RestockingList $restockingList): int
    {
        if ($restockingList->current()['to_buy'] === []) {
            $this->components->info('Nothing to buy: no digest sent.');

            return self::SUCCESS;
        }

        $recipients = User::permission(Permission::BarRestockingShop->value)
            ->whereNotNull('email')
            ->pluck('id');

        foreach ($recipients as $userId) {
            SendBarRestockingDigestJob::dispatch((int) $userId);
        }

        $this->components->info("Digest queued for {$recipients->count()} store keeper(s).");

        return self::SUCCESS;
    }
}
